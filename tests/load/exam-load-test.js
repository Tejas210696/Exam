import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';

// Custom metrics
const errorRate = new Rate('errors');
const examStartTime = new Trend('exam_start_time');
const questionLoadTime = new Trend('question_load_time');
const answerSubmitTime = new Trend('answer_submit_time');

export const options = {
  stages: [
    // Ramp-up
    { duration: '2m', target: 100 }, // Ramp up to 100 users
    { duration: '5m', target: 100 }, // Stay at 100 users
    { duration: '2m', target: 500 }, // Ramp up to 500 users
    { duration: '10m', target: 500 }, // Stay at 500 users
    { duration: '2m', target: 1000 }, // Ramp up to 1000 users
    { duration: '10m', target: 1000 }, // Stay at 1000 users
    { duration: '5m', target: 0 }, // Ramp down
  ],
  thresholds: {
    http_req_duration: ['p(95)<2000'], // 95% of requests should be below 2s
    http_req_failed: ['rate<0.1'], // Error rate should be less than 10%
    errors: ['rate<0.1'],
    exam_start_time: ['p(95)<5000'],
    question_load_time: ['p(95)<1000'],
    answer_submit_time: ['p(95)<500'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';

// Test data
const users = [
  { email: 'student1@example.com', password: 'password123' },
  { email: 'student2@example.com', password: 'password123' },
  { email: 'student3@example.com', password: 'password123' },
];

const sampleAnswers = [
  { type: 'SINGLE_CHOICE', answer: 'a' },
  { type: 'SINGLE_CHOICE', answer: 'b' },
  { type: 'SINGLE_CHOICE', answer: 'c' },
  { type: 'MULTIPLE_CHOICE', answer: ['a', 'c'] },
  { type: 'NUMERICAL', answer: 42 },
];

export default function () {
  // Select random user
  const user = users[Math.floor(Math.random() * users.length)];
  
  // Login
  const loginResponse = http.post(`${BASE_URL}/api/v1/auth/login`, {
    email: user.email,
    password: user.password,
  }, {
    headers: { 'Content-Type': 'application/json' },
  });

  const loginCheck = check(loginResponse, {
    'login successful': (r) => r.status === 200,
    'token received': (r) => r.json('data.accessToken') !== '',
  });

  if (!loginCheck) {
    errorRate.add(1);
    return;
  }

  const token = loginResponse.json('data.accessToken');
  const headers = {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json',
  };

  // Get available exams
  const examsResponse = http.get(`${BASE_URL}/api/v1/exams/available`, { headers });
  
  check(examsResponse, {
    'exams loaded': (r) => r.status === 200,
    'has exams': (r) => r.json('data').length > 0,
  }) || errorRate.add(1);

  const exams = examsResponse.json('data');
  if (!exams || exams.length === 0) {
    return;
  }

  const selectedExam = exams[Math.floor(Math.random() * exams.length)];

  // Start exam
  const startTime = Date.now();
  const startExamResponse = http.post(
    `${BASE_URL}/api/v1/exams/${selectedExam.id}/start`,
    {},
    { headers }
  );

  const startCheck = check(startExamResponse, {
    'exam started': (r) => r.status === 200,
    'submission created': (r) => r.json('data.submissionId') !== '',
  });

  if (startCheck) {
    examStartTime.add(Date.now() - startTime);
  } else {
    errorRate.add(1);
    return;
  }

  const submissionId = startExamResponse.json('data.submissionId');

  // Get exam questions
  const questionsTime = Date.now();
  const questionsResponse = http.get(
    `${BASE_URL}/api/v1/exams/${selectedExam.id}/questions`,
    { headers }
  );

  check(questionsResponse, {
    'questions loaded': (r) => r.status === 200,
    'has questions': (r) => r.json('data').length > 0,
  }) && questionLoadTime.add(Date.now() - questionsTime) || errorRate.add(1);

  const questions = questionsResponse.json('data');
  if (!questions || questions.length === 0) {
    return;
  }

  // Answer random questions (simulate partial exam completion)
  const questionsToAnswer = Math.min(5, questions.length);
  
  for (let i = 0; i < questionsToAnswer; i++) {
    const question = questions[i];
    const answerTime = Date.now();
    
    // Simulate thinking time
    sleep(Math.random() * 3 + 1); // 1-4 seconds
    
    // Prepare answer based on question type
    let answerData = {
      questionId: question.id,
      timeSpent: Math.floor(Math.random() * 120) + 30, // 30-150 seconds
      isAnswered: true,
      visitCount: 1,
    };

    // Add type-specific answer
    switch (question.type) {
      case 'SINGLE_CHOICE':
        answerData.selectedOptions = [question.options[Math.floor(Math.random() * question.options.length)].id];
        break;
      case 'MULTIPLE_CHOICE':
        const numSelections = Math.floor(Math.random() * 2) + 1;
        const selectedOptions = [];
        for (let j = 0; j < numSelections; j++) {
          const option = question.options[Math.floor(Math.random() * question.options.length)];
          if (!selectedOptions.includes(option.id)) {
            selectedOptions.push(option.id);
          }
        }
        answerData.selectedOptions = selectedOptions;
        break;
      case 'NUMERICAL':
        answerData.numericalAnswer = Math.floor(Math.random() * 100);
        break;
      case 'FILL_BLANK':
      case 'ESSAY':
        answerData.textAnswer = `Sample answer for question ${i + 1}`;
        break;
    }

    // Submit answer
    const submitResponse = http.post(
      `${BASE_URL}/api/v1/submissions/${submissionId}/answers`,
      JSON.stringify(answerData),
      { headers }
    );

    const submitCheck = check(submitResponse, {
      'answer submitted': (r) => r.status === 200,
    });

    if (submitCheck) {
      answerSubmitTime.add(Date.now() - answerTime);
    } else {
      errorRate.add(1);
    }

    // Random chance to mark for review
    if (Math.random() < 0.2) { // 20% chance
      http.post(
        `${BASE_URL}/api/v1/submissions/${submissionId}/mark-review`,
        JSON.stringify({ questionId: question.id }),
        { headers }
      );
    }

    sleep(0.5); // Brief pause between questions
  }

  // Simulate some users submitting the exam
  if (Math.random() < 0.3) { // 30% chance to submit
    const submitExamResponse = http.post(
      `${BASE_URL}/api/v1/submissions/${submissionId}/submit`,
      {},
      { headers }
    );

    check(submitExamResponse, {
      'exam submitted': (r) => r.status === 200,
    }) || errorRate.add(1);
  }

  // Get user dashboard (simulates post-exam activity)
  const dashboardResponse = http.get(`${BASE_URL}/api/v1/users/dashboard`, { headers });
  
  check(dashboardResponse, {
    'dashboard loaded': (r) => r.status === 200,
  }) || errorRate.add(1);

  // Logout
  http.post(`${BASE_URL}/api/v1/auth/logout`, {}, { headers });

  sleep(1);
}

export function handleSummary(data) {
  return {
    'load-test-results.json': JSON.stringify(data, null, 2),
    'load-test-summary.txt': textSummary(data, { indent: ' ', enableColors: true }),
  };
}

function textSummary(data, options = {}) {
  const indent = options.indent || '';
  const enableColors = options.enableColors || false;
  
  let summary = `${indent}Load Test Summary\n`;
  summary += `${indent}================\n\n`;
  
  // Test duration
  const testDuration = Math.round(data.state.testRunDurationMs / 1000);
  summary += `${indent}Test Duration: ${testDuration}s\n`;
  
  // VUs
  summary += `${indent}Max VUs: ${data.metrics.vus_max.values.max}\n`;
  
  // Requests
  const totalRequests = data.metrics.http_reqs.values.count;
  const requestRate = Math.round(data.metrics.http_reqs.values.rate * 100) / 100;
  summary += `${indent}Total Requests: ${totalRequests}\n`;
  summary += `${indent}Request Rate: ${requestRate} req/s\n`;
  
  // Response times
  const avgResponseTime = Math.round(data.metrics.http_req_duration.values.avg);
  const p95ResponseTime = Math.round(data.metrics.http_req_duration.values['p(95)']);
  summary += `${indent}Avg Response Time: ${avgResponseTime}ms\n`;
  summary += `${indent}95th Percentile: ${p95ResponseTime}ms\n`;
  
  // Error rate
  const errorRate = Math.round(data.metrics.http_req_failed.values.rate * 10000) / 100;
  summary += `${indent}Error Rate: ${errorRate}%\n`;
  
  return summary;
}