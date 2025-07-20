#!/usr/bin/env node

const { Client } = require('pg');
const { MongoClient } = require('mongodb');
const bcrypt = require('bcryptjs');
const { faker } = require('@faker-js/faker');

// Configuration
const config = {
  postgres: {
    connectionString: process.env.POSTGRES_URL || 'postgresql://jee_user:jee_password@localhost:5432/jee_portal'
  },
  mongodb: {
    url: process.env.MONGODB_URL || 'mongodb://jee_user:jee_password@localhost:27017/jee_portal'
  }
};

// Question templates by subject
const questionTemplates = {
  MATHEMATICS: [
    {
      topics: ['Algebra', 'Calculus', 'Geometry', 'Trigonometry', 'Statistics'],
      templates: [
        'If x + y = {a} and x - y = {b}, what is the value of x?',
        'Find the derivative of f(x) = {a}x² + {b}x + {c}',
        'What is the area of a circle with radius {a} cm?',
        'Solve for x: {a}x² + {b}x + {c} = 0',
        'Find the limit of (x² - {a}) / (x - {b}) as x approaches {b}'
      ]
    }
  ],
  PHYSICS: [
    {
      topics: ['Mechanics', 'Thermodynamics', 'Optics', 'Electricity', 'Modern Physics'],
      templates: [
        'A ball is thrown with initial velocity {a} m/s. What is the maximum height? (g = 10 m/s²)',
        'Calculate the force required to accelerate a {a} kg object at {b} m/s²',
        'What is the resistance of a wire with length {a} m and cross-sectional area {b} mm²?',
        'Find the wavelength of light with frequency {a} Hz (c = 3×10⁸ m/s)',
        'Calculate the kinetic energy of a {a} kg object moving at {b} m/s'
      ]
    }
  ],
  CHEMISTRY: [
    {
      topics: ['Organic Chemistry', 'Inorganic Chemistry', 'Physical Chemistry', 'Analytical Chemistry'],
      templates: [
        'How many moles are in {a} grams of NaCl? (Molar mass = 58.5 g/mol)',
        'What is the pH of a {a} M HCl solution?',
        'Balance the equation: C₂H₆ + O₂ → CO₂ + H₂O',
        'Calculate the molarity of {a} g of NaOH in {b} L of solution',
        'What is the oxidation state of sulfur in H₂SO₄?'
      ]
    }
  ]
};

// Utility functions
function generateRandomNumber(min, max) {
  return Math.floor(Math.random() * (max - min + 1)) + min;
}

function generateOptions(correctAnswer, type = 'SINGLE_CHOICE') {
  const options = [];
  const letters = ['a', 'b', 'c', 'd'];
  
  for (let i = 0; i < 4; i++) {
    const isCorrect = i === 0; // First option is always correct initially
    let text;
    
    if (typeof correctAnswer === 'number') {
      if (isCorrect) {
        text = correctAnswer.toString();
      } else {
        // Generate wrong options
        const variation = correctAnswer * (0.1 + Math.random() * 0.4);
        text = (correctAnswer + (Math.random() > 0.5 ? variation : -variation)).toFixed(2);
      }
    } else {
      text = isCorrect ? correctAnswer : faker.lorem.words(2);
    }
    
    options.push({
      id: letters[i],
      text: text,
      isCorrect: isCorrect
    });
  }
  
  // Shuffle options
  for (let i = options.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [options[i], options[j]] = [options[j], options[i]];
  }
  
  return options;
}

function generateQuestion(subject, difficulty) {
  const templates = questionTemplates[subject];
  if (!templates) return null;
  
  const template = templates[0];
  const topic = template.topics[Math.floor(Math.random() * template.topics.length)];
  const questionTemplate = template.templates[Math.floor(Math.random() * template.templates.length)];
  
  // Replace placeholders with random values
  let question = questionTemplate;
  const placeholders = question.match(/{[a-z]}/g) || [];
  placeholders.forEach(placeholder => {
    const value = generateRandomNumber(1, 20);
    question = question.replace(placeholder, value);
  });
  
  const type = Math.random() > 0.8 ? 'MULTIPLE_CHOICE' : 'SINGLE_CHOICE';
  const marks = difficulty === 'EASY' ? 2 : difficulty === 'MEDIUM' ? 4 : 6;
  const negativeMarks = marks * 0.25;
  
  // Generate correct answer
  let correctAnswer;
  if (subject === 'MATHEMATICS' && question.includes('x =')) {
    correctAnswer = generateRandomNumber(1, 10);
  } else {
    correctAnswer = generateRandomNumber(10, 100);
  }
  
  const options = generateOptions(correctAnswer, type);
  const correctOption = options.find(opt => opt.isCorrect);
  
  return {
    type,
    subject,
    topic,
    difficulty,
    question,
    options: options.map(opt => ({ id: opt.id, text: opt.text })),
    correctAnswer: type === 'SINGLE_CHOICE' ? correctOption.id : [correctOption.id],
    explanation: `The correct answer is ${correctAnswer}. This is because...`,
    marks,
    negativeMarks,
    timeLimit: 120,
    tags: [topic.toLowerCase(), subject.toLowerCase()],
    createdBy: 'system',
    createdAt: new Date(),
    updatedAt: new Date(),
    metadata: {
      averageTime: generateRandomNumber(60, 180),
      successRate: Math.random(),
      timesUsed: generateRandomNumber(0, 100),
      lastUsed: faker.date.past()
    }
  };
}

async function seedDatabase() {
  console.log('🌱 Starting database seeding...');
  
  let pgClient, mongoClient, db;
  
  try {
    // Connect to PostgreSQL
    console.log('📊 Connecting to PostgreSQL...');
    pgClient = new Client(config.postgres);
    await pgClient.connect();
    console.log('✅ PostgreSQL connected');
    
    // Connect to MongoDB
    console.log('📄 Connecting to MongoDB...');
    mongoClient = new MongoClient(config.mongodb.url);
    await mongoClient.connect();
    db = mongoClient.db('jee_portal');
    console.log('✅ MongoDB connected');
    
    // Seed PostgreSQL with users
    console.log('👥 Seeding users (100,000)...');
    await seedUsers(pgClient);
    
    // Seed MongoDB with questions
    console.log('❓ Seeding questions (10,000)...');
    await seedQuestions(db);
    
    // Create sample exams
    console.log('📝 Creating sample exams...');
    await createSampleExams(pgClient, db);
    
    console.log('🎉 Database seeding completed successfully!');
    
  } catch (error) {
    console.error('❌ Error seeding database:', error);
    process.exit(1);
  } finally {
    if (pgClient) await pgClient.end();
    if (mongoClient) await mongoClient.close();
  }
}

async function seedUsers(pgClient) {
  const batchSize = 1000;
  const totalUsers = 100000;
  const batches = Math.ceil(totalUsers / batchSize);
  
  for (let batch = 0; batch < batches; batch++) {
    const users = [];
    const profiles = [];
    
    for (let i = 0; i < batchSize && (batch * batchSize + i) < totalUsers; i++) {
      const userId = faker.string.uuid();
      const email = faker.internet.email();
      const hashedPassword = await bcrypt.hash('password123', 12);
      
      users.push([
        userId,
        email,
        hashedPassword,
        faker.person.firstName(),
        faker.person.lastName(),
        'STUDENT',
        'ACTIVE',
        faker.phone.number(),
        faker.date.birthdate({ min: 16, max: 25, mode: 'age' }),
        null, // profile_image
        new Date(),
        new Date(),
        null // last_login
      ]);
      
      profiles.push([
        faker.string.uuid(),
        userId,
        faker.location.streetAddress(),
        faker.location.city(),
        faker.location.state(),
        faker.location.country(),
        faker.location.zipCode(),
        'en',
        'UTC',
        true, // email_notifications
        false, // sms_notifications
        true, // push_notifications
        new Date(),
        new Date()
      ]);
    }
    
    // Insert users
    const userValues = users.map((_, i) => `($${i * 12 + 1}, $${i * 12 + 2}, $${i * 12 + 3}, $${i * 12 + 4}, $${i * 12 + 5}, $${i * 12 + 6}, $${i * 12 + 7}, $${i * 12 + 8}, $${i * 12 + 9}, $${i * 12 + 10}, $${i * 12 + 11}, $${i * 12 + 12})`).join(', ');
    const userParams = users.flat();
    
    await pgClient.query(`
      INSERT INTO users (id, email, password_hash, first_name, last_name, role, status, phone, date_of_birth, profile_image, created_at, updated_at, last_login)
      VALUES ${userValues}
      ON CONFLICT (email) DO NOTHING
    `, userParams);
    
    // Insert profiles
    const profileValues = profiles.map((_, i) => `($${i * 14 + 1}, $${i * 14 + 2}, $${i * 14 + 3}, $${i * 14 + 4}, $${i * 14 + 5}, $${i * 14 + 6}, $${i * 14 + 7}, $${i * 14 + 8}, $${i * 14 + 9}, $${i * 14 + 10}, $${i * 14 + 11}, $${i * 14 + 12}, $${i * 14 + 13}, $${i * 14 + 14})`).join(', ');
    const profileParams = profiles.flat();
    
    await pgClient.query(`
      INSERT INTO user_profiles (id, user_id, address_street, address_city, address_state, address_country, address_zip_code, language, timezone, email_notifications, sms_notifications, push_notifications, created_at, updated_at)
      VALUES ${profileValues}
    `, profileParams);
    
    console.log(`   📊 Batch ${batch + 1}/${batches} completed (${Math.min((batch + 1) * batchSize, totalUsers)} users)`);
  }
}

async function seedQuestions(db) {
  const batchSize = 500;
  const totalQuestions = 10000;
  const batches = Math.ceil(totalQuestions / batchSize);
  
  const subjects = ['MATHEMATICS', 'PHYSICS', 'CHEMISTRY'];
  const difficulties = ['EASY', 'MEDIUM', 'HARD'];
  
  for (let batch = 0; batch < batches; batch++) {
    const questions = [];
    
    for (let i = 0; i < batchSize && (batch * batchSize + i) < totalQuestions; i++) {
      const subject = subjects[Math.floor(Math.random() * subjects.length)];
      const difficulty = difficulties[Math.floor(Math.random() * difficulties.length)];
      
      const question = generateQuestion(subject, difficulty);
      if (question) {
        questions.push(question);
      }
    }
    
    if (questions.length > 0) {
      await db.collection('questions').insertMany(questions);
    }
    
    console.log(`   📄 Batch ${batch + 1}/${batches} completed (${Math.min((batch + 1) * batchSize, totalQuestions)} questions)`);
  }
}

async function createSampleExams(pgClient, db) {
  // Get some questions from MongoDB
  const mathQuestions = await db.collection('questions').find({ subject: 'MATHEMATICS' }).limit(30).toArray();
  const physicsQuestions = await db.collection('questions').find({ subject: 'PHYSICS' }).limit(30).toArray();
  const chemistryQuestions = await db.collection('questions').find({ subject: 'CHEMISTRY' }).limit(30).toArray();
  
  // Get admin user
  const adminResult = await pgClient.query("SELECT id FROM users WHERE role = 'SUPER_ADMIN' LIMIT 1");
  const adminId = adminResult.rows[0]?.id;
  
  if (!adminId) {
    console.log('❌ No admin user found, skipping exam creation');
    return;
  }
  
  const sampleExams = [
    {
      title: 'JEE Main Mock Test 1',
      description: 'Comprehensive mock test covering Mathematics, Physics, and Chemistry',
      type: 'JEE_MAIN',
      duration: 180, // 3 hours
      totalMarks: 300,
      passingMarks: 90,
      questions: [...mathQuestions.slice(0, 25), ...physicsQuestions.slice(0, 25), ...chemistryQuestions.slice(0, 25)]
    },
    {
      title: 'Mathematics Practice Test',
      description: 'Focused practice test for Mathematics',
      type: 'PRACTICE',
      duration: 90,
      totalMarks: 120,
      passingMarks: 36,
      questions: mathQuestions
    },
    {
      title: 'Physics Challenge',
      description: 'Advanced Physics problems',
      type: 'PRACTICE',
      duration: 120,
      totalMarks: 150,
      passingMarks: 45,
      questions: physicsQuestions
    }
  ];
  
  for (const examData of sampleExams) {
    const examId = faker.string.uuid();
    const startTime = faker.date.future();
    const endTime = new Date(startTime.getTime() + examData.duration * 60000);
    
    // Insert exam
    await pgClient.query(`
      INSERT INTO exams (id, title, description, type, status, duration, total_marks, passing_marks, start_time, end_time, instructions, settings, created_by, created_at, updated_at)
      VALUES ($1, $2, $3, $4, 'SCHEDULED', $5, $6, $7, $8, $9, $10, $11, $12, NOW(), NOW())
    `, [
      examId,
      examData.title,
      examData.description,
      examData.type,
      examData.duration,
      examData.totalMarks,
      examData.passingMarks,
      startTime,
      endTime,
      JSON.stringify([
        'Read all instructions carefully',
        'Each question carries equal marks',
        'There is negative marking for wrong answers',
        'Use of calculator is not allowed'
      ]),
      JSON.stringify({
        shuffleQuestions: true,
        shuffleOptions: true,
        allowReview: true,
        showResult: true,
        negativeMarking: true,
        negativeMarkingRatio: 0.25,
        proctoring: {
          enabled: true,
          faceDetection: true,
          screenRecording: false,
          audioRecording: false,
          tabSwitchDetection: true,
          fullScreenRequired: true,
          violationThreshold: 3
        },
        security: {
          browserLockdown: true,
          disableCopyPaste: true,
          disableRightClick: true,
          disableF12: true,
          preventScreenshot: true,
          watermark: true
        }
      }),
      adminId
    ]);
    
    console.log(`   📝 Created exam: ${examData.title}`);
  }
}

// Run the seeding
if (require.main === module) {
  seedDatabase().catch(console.error);
}

module.exports = { seedDatabase };