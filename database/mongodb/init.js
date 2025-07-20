// MongoDB initialization script for JEE Portal

// Switch to jee_portal database
db = db.getSiblingDB('jee_portal');

// Create collections with validation schemas
db.createCollection('questions', {
  validator: {
    $jsonSchema: {
      bsonType: 'object',
      required: ['type', 'subject', 'difficulty', 'question', 'correctAnswer', 'marks'],
      properties: {
        _id: { bsonType: 'objectId' },
        type: { 
          enum: ['SINGLE_CHOICE', 'MULTIPLE_CHOICE', 'NUMERICAL', 'TRUE_FALSE', 'FILL_BLANK', 'ESSAY'] 
        },
        subject: { 
          enum: ['MATHEMATICS', 'PHYSICS', 'CHEMISTRY', 'BIOLOGY', 'ENGLISH', 'GENERAL_KNOWLEDGE'] 
        },
        topic: { bsonType: 'string' },
        difficulty: { 
          enum: ['EASY', 'MEDIUM', 'HARD', 'EXPERT'] 
        },
        question: { bsonType: 'string' },
        options: { 
          bsonType: 'array',
          items: {
            bsonType: 'object',
            properties: {
              id: { bsonType: 'string' },
              text: { bsonType: 'string' },
              isCorrect: { bsonType: 'bool' }
            }
          }
        },
        correctAnswer: { 
          oneOf: [
            { bsonType: 'string' },
            { bsonType: 'array', items: { bsonType: 'string' } }
          ]
        },
        explanation: { bsonType: 'string' },
        marks: { bsonType: 'number', minimum: 0 },
        negativeMarks: { bsonType: 'number', minimum: 0 },
        timeLimit: { bsonType: 'number', minimum: 0 },
        tags: { bsonType: 'array', items: { bsonType: 'string' } },
        createdBy: { bsonType: 'string' },
        createdAt: { bsonType: 'date' },
        updatedAt: { bsonType: 'date' },
        metadata: { bsonType: 'object' }
      }
    }
  }
});

db.createCollection('submissions', {
  validator: {
    $jsonSchema: {
      bsonType: 'object',
      required: ['examId', 'userId', 'startTime', 'status', 'answers'],
      properties: {
        _id: { bsonType: 'objectId' },
        examId: { bsonType: 'string' },
        userId: { bsonType: 'string' },
        startTime: { bsonType: 'date' },
        endTime: { bsonType: 'date' },
        status: { 
          enum: ['IN_PROGRESS', 'SUBMITTED', 'AUTO_SUBMITTED', 'DISQUALIFIED'] 
        },
        answers: {
          bsonType: 'array',
          items: {
            bsonType: 'object',
            properties: {
              questionId: { bsonType: 'string' },
              selectedOptions: { bsonType: 'array', items: { bsonType: 'string' } },
              textAnswer: { bsonType: 'string' },
              numericalAnswer: { bsonType: 'number' },
              timeSpent: { bsonType: 'number' },
              isMarkedForReview: { bsonType: 'bool' },
              isAnswered: { bsonType: 'bool' },
              visitCount: { bsonType: 'number' }
            }
          }
        },
        timeSpent: { bsonType: 'number' },
        violations: { bsonType: 'array' },
        metadata: { bsonType: 'object' }
      }
    }
  }
});

db.createCollection('proctoring_sessions', {
  validator: {
    $jsonSchema: {
      bsonType: 'object',
      required: ['examId', 'userId', 'startTime'],
      properties: {
        _id: { bsonType: 'objectId' },
        examId: { bsonType: 'string' },
        userId: { bsonType: 'string' },
        startTime: { bsonType: 'date' },
        endTime: { bsonType: 'date' },
        violations: {
          bsonType: 'array',
          items: {
            bsonType: 'object',
            properties: {
              id: { bsonType: 'string' },
              type: { 
                enum: ['FACE_NOT_DETECTED', 'MULTIPLE_FACES', 'TAB_SWITCH', 'FULL_SCREEN_EXIT', 'SUSPICIOUS_ACTIVITY', 'AUDIO_ANOMALY'] 
              },
              timestamp: { bsonType: 'date' },
              severity: { 
                enum: ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'] 
              },
              description: { bsonType: 'string' },
              evidence: { bsonType: 'string' },
              resolved: { bsonType: 'bool' }
            }
          }
        },
        screenshots: { bsonType: 'array', items: { bsonType: 'string' } },
        recordings: { bsonType: 'array', items: { bsonType: 'string' } }
      }
    }
  }
});

// Create indexes for better performance
db.questions.createIndex({ subject: 1, difficulty: 1 });
db.questions.createIndex({ type: 1 });
db.questions.createIndex({ tags: 1 });
db.questions.createIndex({ createdBy: 1 });
db.questions.createIndex({ createdAt: -1 });
db.questions.createIndex({ "metadata.successRate": -1 });

db.submissions.createIndex({ examId: 1, userId: 1 }, { unique: true });
db.submissions.createIndex({ examId: 1 });
db.submissions.createIndex({ userId: 1 });
db.submissions.createIndex({ status: 1 });
db.submissions.createIndex({ startTime: -1 });

db.proctoring_sessions.createIndex({ examId: 1, userId: 1 });
db.proctoring_sessions.createIndex({ startTime: -1 });
db.proctoring_sessions.createIndex({ "violations.type": 1 });
db.proctoring_sessions.createIndex({ "violations.severity": 1 });

// Insert sample questions
const sampleQuestions = [
  // Mathematics Questions
  {
    type: 'SINGLE_CHOICE',
    subject: 'MATHEMATICS',
    topic: 'Algebra',
    difficulty: 'MEDIUM',
    question: 'If x + y = 10 and x - y = 4, what is the value of x?',
    options: [
      { id: 'a', text: '6', isCorrect: false },
      { id: 'b', text: '7', isCorrect: true },
      { id: 'c', text: '8', isCorrect: false },
      { id: 'd', text: '9', isCorrect: false }
    ],
    correctAnswer: 'b',
    explanation: 'Adding the equations: 2x = 14, so x = 7',
    marks: 4,
    negativeMarks: 1,
    timeLimit: 120,
    tags: ['algebra', 'linear-equations'],
    createdBy: 'admin',
    createdAt: new Date(),
    updatedAt: new Date(),
    metadata: {
      averageTime: 90,
      successRate: 0.75,
      timesUsed: 150
    }
  },
  
  // Physics Questions
  {
    type: 'SINGLE_CHOICE',
    subject: 'PHYSICS',
    topic: 'Mechanics',
    difficulty: 'HARD',
    question: 'A ball is thrown vertically upward with an initial velocity of 20 m/s. What is the maximum height reached? (g = 10 m/s²)',
    options: [
      { id: 'a', text: '10 m', isCorrect: false },
      { id: 'b', text: '15 m', isCorrect: false },
      { id: 'c', text: '20 m', isCorrect: true },
      { id: 'd', text: '25 m', isCorrect: false }
    ],
    correctAnswer: 'c',
    explanation: 'Using v² = u² + 2gh, at maximum height v=0, so h = u²/2g = 400/20 = 20m',
    marks: 4,
    negativeMarks: 1,
    timeLimit: 180,
    tags: ['mechanics', 'projectile-motion'],
    createdBy: 'admin',
    createdAt: new Date(),
    updatedAt: new Date(),
    metadata: {
      averageTime: 150,
      successRate: 0.60,
      timesUsed: 120
    }
  },

  // Chemistry Questions
  {
    type: 'MULTIPLE_CHOICE',
    subject: 'CHEMISTRY',
    topic: 'Organic Chemistry',
    difficulty: 'MEDIUM',
    question: 'Which of the following are characteristics of alkenes?',
    options: [
      { id: 'a', text: 'Contain C=C double bonds', isCorrect: true },
      { id: 'b', text: 'Are saturated hydrocarbons', isCorrect: false },
      { id: 'c', text: 'Show geometric isomerism', isCorrect: true },
      { id: 'd', text: 'Are more reactive than alkanes', isCorrect: true }
    ],
    correctAnswer: ['a', 'c', 'd'],
    explanation: 'Alkenes have C=C double bonds, are unsaturated, show geometric isomerism, and are more reactive than alkanes',
    marks: 4,
    negativeMarks: 1,
    timeLimit: 120,
    tags: ['organic-chemistry', 'alkenes', 'hydrocarbons'],
    createdBy: 'admin',
    createdAt: new Date(),
    updatedAt: new Date(),
    metadata: {
      averageTime: 110,
      successRate: 0.55,
      timesUsed: 90
    }
  },

  // Numerical type question
  {
    type: 'NUMERICAL',
    subject: 'MATHEMATICS',
    topic: 'Calculus',
    difficulty: 'HARD',
    question: 'Find the value of the definite integral ∫₀¹ x² dx',
    correctAnswer: '0.333',
    explanation: 'The integral of x² is x³/3. Evaluating from 0 to 1: (1³/3) - (0³/3) = 1/3 ≈ 0.333',
    marks: 4,
    negativeMarks: 0,
    timeLimit: 300,
    tags: ['calculus', 'integration', 'definite-integral'],
    createdBy: 'admin',
    createdAt: new Date(),
    updatedAt: new Date(),
    metadata: {
      averageTime: 240,
      successRate: 0.40,
      timesUsed: 75
    }
  }
];

// Insert sample questions
db.questions.insertMany(sampleQuestions);

// Create sample exam submission
const sampleSubmission = {
  examId: '550e8400-e29b-41d4-a716-446655440000',
  userId: '550e8400-e29b-41d4-a716-446655440001',
  startTime: new Date(),
  status: 'IN_PROGRESS',
  answers: [
    {
      questionId: db.questions.findOne({subject: 'MATHEMATICS'})._id.toString(),
      selectedOptions: ['b'],
      timeSpent: 95,
      isMarkedForReview: false,
      isAnswered: true,
      visitCount: 2
    }
  ],
  timeSpent: 95,
  violations: [],
  metadata: {
    browserInfo: {
      name: 'Chrome',
      version: '120.0.0',
      userAgent: 'Mozilla/5.0...'
    },
    deviceInfo: {
      type: 'desktop',
      os: 'Windows 10',
      screenResolution: '1920x1080'
    },
    ipAddress: '192.168.1.100'
  },
  createdAt: new Date(),
  updatedAt: new Date()
};

db.submissions.insertOne(sampleSubmission);

print('MongoDB initialization completed successfully!');
print('Collections created: questions, submissions, proctoring_sessions');
print('Sample data inserted: ' + sampleQuestions.length + ' questions, 1 submission');