<?php

/**
 * JEE Portal Laravel/PHP Data Seeding Script
 * 
 * This script seeds the database with:
 * - 100,000 students
 * - 10,000 questions across subjects
 * - Sample exams and results
 * - Admin users and roles
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Faker\Factory as Faker;

class DatabaseSeeder
{
    private PDO $mysql;
    private MongoDB\Client $mongodb;
    private Redis $redis;
    private Faker\Generator $faker;
    
    // Configuration
    private const BATCH_SIZE = 1000;
    private const TOTAL_STUDENTS = 100000;
    private const TOTAL_QUESTIONS = 10000;
    private const TOTAL_SAMPLE_EXAMS = 50;
    
    // JEE Subjects and topics
    private const SUBJECTS = [
        'PHYSICS' => [
            'Mechanics', 'Thermodynamics', 'Waves and Oscillations', 
            'Electromagnetism', 'Optics', 'Modern Physics', 'Electronics'
        ],
        'CHEMISTRY' => [
            'Physical Chemistry', 'Organic Chemistry', 'Inorganic Chemistry',
            'Chemical Bonding', 'Thermochemistry', 'Electrochemistry'
        ],
        'MATHEMATICS' => [
            'Algebra', 'Calculus', 'Coordinate Geometry', 'Trigonometry',
            'Statistics and Probability', 'Vectors', 'Complex Numbers'
        ]
    ];
    
    private const DIFFICULTY_LEVELS = ['EASY', 'MEDIUM', 'HARD'];
    private const QUESTION_TYPES = ['SINGLE_CORRECT', 'MULTIPLE_CORRECT', 'NUMERICAL', 'ASSERTION_REASON'];
    
    public function __construct()
    {
        $this->faker = Faker::create('en_IN');
        $this->connectDatabases();
        $this->setupProgressTracking();
    }
    
    private function connectDatabases(): void
    {
        try {
            // MySQL connection
            $this->mysql = new PDO(
                'mysql:host=mysql;dbname=jee_portal;charset=utf8mb4',
                'jee_user',
                'jee_password',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
            
            // MongoDB connection
            $this->mongodb = new MongoDB\Client('mongodb://jee_user:jee_password@mongodb:27017');
            
            // Redis connection
            $this->redis = new Redis();
            $this->redis->connect('redis', 6379);
            
            $this->log("✅ Database connections established");
            
        } catch (Exception $e) {
            $this->log("❌ Database connection failed: " . $e->getMessage());
            exit(1);
        }
    }
    
    private function setupProgressTracking(): void
    {
        // Clear previous progress
        $this->redis->del('seeding:progress');
        $this->redis->del('seeding:stats');
    }
    
    public function seedAll(): void
    {
        $startTime = microtime(true);
        
        $this->log("🌱 Starting comprehensive database seeding...");
        $this->log("📊 Target: " . number_format(self::TOTAL_STUDENTS) . " students, " . number_format(self::TOTAL_QUESTIONS) . " questions");
        
        try {
            // Seed in order of dependencies
            $this->seedUsers();
            $this->seedQuestions();
            $this->seedExams();
            $this->seedSampleSubmissions();
            $this->seedAnalyticsData();
            
            $duration = microtime(true) - $startTime;
            $this->log("🎉 Seeding completed successfully in " . round($duration, 2) . " seconds");
            $this->displaySeedingStats();
            
        } catch (Exception $e) {
            $this->log("❌ Seeding failed: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
            exit(1);
        }
    }
    
    private function seedUsers(): void
    {
        $this->log("👥 Seeding users...");
        
        $totalBatches = ceil(self::TOTAL_STUDENTS / self::BATCH_SIZE);
        $currentBatch = 0;
        
        // Prepare statement
        $stmt = $this->mysql->prepare("
            INSERT INTO users (id, email, password, first_name, last_name, role, status, 
                              phone, date_of_birth, created_at, updated_at, email_verified_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $profileStmt = $this->mysql->prepare("
            INSERT INTO user_profiles (id, user_id, address_street, address_city, 
                                     address_state, address_country, address_zip_code,
                                     created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        // Start transaction for better performance
        $this->mysql->beginTransaction();
        
        for ($i = 0; $i < self::TOTAL_STUDENTS; $i++) {
            $userId = $this->generateUuid();
            $profileId = $this->generateUuid();
            $firstName = $this->faker->firstName;
            $lastName = $this->faker->lastName;
            $email = $this->generateUniqueEmail($firstName, $lastName, $i);
            $phone = $this->faker->phoneNumber;
            $dob = $this->faker->dateTimeBetween('-25 years', '-17 years')->format('Y-m-d');
            $now = date('Y-m-d H:i:s');
            
            // Insert user
            $stmt->execute([
                $userId,
                $email,
                '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewdBPj0kEjWNUKhm', // password
                $firstName,
                $lastName,
                'STUDENT',
                'ACTIVE',
                $phone,
                $dob,
                $now,
                $now,
                $now
            ]);
            
            // Insert user profile
            $profileStmt->execute([
                $profileId,
                $userId,
                $this->faker->streetAddress,
                $this->faker->city,
                $this->faker->state,
                'India',
                $this->faker->postcode,
                $now,
                $now
            ]);
            
            // Commit batch
            if (($i + 1) % self::BATCH_SIZE === 0) {
                $this->mysql->commit();
                $this->mysql->beginTransaction();
                $currentBatch++;
                
                $progress = ($currentBatch / $totalBatches) * 100;
                $this->updateProgress('users', $progress, $currentBatch * self::BATCH_SIZE);
                $this->log("  📈 Users: {$currentBatch}/{$totalBatches} batches (" . round($progress, 1) . "%)");
            }
        }
        
        // Commit remaining records
        $this->mysql->commit();
        
        $this->log("✅ Users seeded: " . number_format(self::TOTAL_STUDENTS) . " students");
    }
    
    private function seedQuestions(): void
    {
        $this->log("❓ Seeding questions...");
        
        $collection = $this->mongodb->selectDatabase('jee_portal')->selectCollection('questions');
        $totalBatches = ceil(self::TOTAL_QUESTIONS / self::BATCH_SIZE);
        $currentBatch = 0;
        $batch = [];
        
        for ($i = 0; $i < self::TOTAL_QUESTIONS; $i++) {
            $subject = array_rand(self::SUBJECTS);
            $topics = self::SUBJECTS[$subject];
            $topic = $topics[array_rand($topics)];
            $difficulty = self::DIFFICULTY_LEVELS[array_rand(self::DIFFICULTY_LEVELS)];
            $type = self::QUESTION_TYPES[array_rand(self::QUESTION_TYPES)];
            
            $question = [
                '_id' => new MongoDB\BSON\ObjectId(),
                'subject' => $subject,
                'topic' => $topic,
                'difficulty' => $difficulty,
                'type' => $type,
                'question' => $this->generateQuestion($subject, $topic, $type),
                'options' => $this->generateOptions($type),
                'correctAnswer' => $this->generateCorrectAnswer($type),
                'explanation' => $this->generateExplanation($subject, $topic),
                'marks' => $this->getMarksForDifficulty($difficulty),
                'negativeMarks' => $this->getNegativeMarks($difficulty),
                'tags' => $this->generateTags($subject, $topic),
                'metadata' => [
                    'timesUsed' => 0,
                    'totalAttempts' => 0,
                    'correctAttempts' => 0,
                    'averageTime' => 0,
                    'difficultyIndex' => $this->faker->randomFloat(2, 0.2, 0.9),
                    'discriminationIndex' => $this->faker->randomFloat(2, 0.1, 0.8),
                ],
                'createdAt' => new MongoDB\BSON\UTCDateTime(),
                'updatedAt' => new MongoDB\BSON\UTCDateTime(),
                'createdBy' => 'system',
                'status' => 'ACTIVE',
            ];
            
            $batch[] = $question;
            
            // Insert batch
            if (count($batch) >= self::BATCH_SIZE) {
                $collection->insertMany($batch);
                $batch = [];
                $currentBatch++;
                
                $progress = ($currentBatch / $totalBatches) * 100;
                $this->updateProgress('questions', $progress, $currentBatch * self::BATCH_SIZE);
                $this->log("  📈 Questions: {$currentBatch}/{$totalBatches} batches (" . round($progress, 1) . "%)");
            }
        }
        
        // Insert remaining questions
        if (!empty($batch)) {
            $collection->insertMany($batch);
        }
        
        $this->log("✅ Questions seeded: " . number_format(self::TOTAL_QUESTIONS) . " questions");
    }
    
    private function seedExams(): void
    {
        $this->log("📝 Seeding sample exams...");
        
        $stmt = $this->mysql->prepare("
            INSERT INTO exams (id, title, description, type, status, duration, total_marks,
                              passing_marks, start_time, end_time, instructions, settings,
                              created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $adminUserId = $this->getAdminUserId();
        
        for ($i = 0; $i < self::TOTAL_SAMPLE_EXAMS; $i++) {
            $examId = $this->generateUuid();
            $examType = ['JEE_MAIN', 'JEE_ADVANCED', 'PRACTICE', 'MOCK'][array_rand(['JEE_MAIN', 'JEE_ADVANCED', 'PRACTICE', 'MOCK'])];
            $duration = $examType === 'JEE_MAIN' ? 180 : ($examType === 'JEE_ADVANCED' ? 210 : 120);
            $totalMarks = $examType === 'JEE_MAIN' ? 300 : ($examType === 'JEE_ADVANCED' ? 372 : 200);
            $passingMarks = intval($totalMarks * 0.4);
            
            $startTime = $this->faker->dateTimeBetween('-30 days', '+30 days');
            $endTime = clone $startTime;
            $endTime->add(new DateInterval('PT' . $duration . 'M'));
            
            $instructions = json_encode([
                'general' => [
                    'Read all instructions carefully before starting',
                    'Each question carries equal marks',
                    'There is negative marking for wrong answers',
                    'Use of calculator is not allowed'
                ],
                'navigation' => [
                    'You can navigate between questions using the question palette',
                    'Mark questions for review using the flag button',
                    'Submit the exam only when you are sure'
                ]
            ]);
            
            $settings = json_encode([
                'shuffleQuestions' => true,
                'shuffleOptions' => true,
                'showTimer' => true,
                'allowReview' => true,
                'autoSubmit' => true,
                'proctoring' => [
                    'enabled' => true,
                    'faceDetection' => true,
                    'tabSwitchLimit' => 3,
                    'copyPasteDisabled' => true
                ],
                'questionDistribution' => [
                    'PHYSICS' => 25,
                    'CHEMISTRY' => 25,
                    'MATHEMATICS' => 25
                ]
            ]);
            
            $now = date('Y-m-d H:i:s');
            
            $stmt->execute([
                $examId,
                $this->generateExamTitle($examType, $i),
                $this->generateExamDescription($examType),
                $examType,
                'SCHEDULED',
                $duration,
                $totalMarks,
                $passingMarks,
                $startTime->format('Y-m-d H:i:s'),
                $endTime->format('Y-m-d H:i:s'),
                $instructions,
                $settings,
                $adminUserId,
                $now,
                $now
            ]);
        }
        
        $this->log("✅ Sample exams seeded: " . self::TOTAL_SAMPLE_EXAMS . " exams");
    }
    
    private function seedSampleSubmissions(): void
    {
        $this->log("📊 Seeding sample submissions and results...");
        
        // Get sample users and exams
        $users = $this->mysql->query("SELECT id FROM users WHERE role = 'STUDENT' LIMIT 1000")->fetchAll();
        $exams = $this->mysql->query("SELECT id FROM exams LIMIT 10")->fetchAll();
        
        if (empty($users) || empty($exams)) {
            $this->log("⚠️ No users or exams found for sample submissions");
            return;
        }
        
        $submissionStmt = $this->mysql->prepare("
            INSERT INTO exam_submissions (id, exam_id, user_id, start_time, end_time, status,
                                        time_spent, score, percentage, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $resultStmt = $this->mysql->prepare("
            INSERT INTO exam_results (id, exam_id, user_id, submission_id, total_questions,
                                    attempted_questions, correct_answers, wrong_answers,
                                    unanswered_questions, score, percentage, rank,
                                    subject_wise_results, time_analysis, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $submissionCollection = $this->mongodb->selectDatabase('jee_portal')->selectCollection('submissions');
        
        $totalSubmissions = 0;
        
        foreach ($exams as $exam) {
            $examUsers = array_slice($users, 0, rand(50, 200));
            $rank = 1;
            
            foreach ($examUsers as $user) {
                $submissionId = $this->generateUuid();
                $resultId = $this->generateUuid();
                
                $startTime = $this->faker->dateTimeBetween('-7 days', '-1 day');
                $endTime = clone $startTime;
                $endTime->add(new DateInterval('PT' . rand(90, 180) . 'M'));
                
                $totalQuestions = 75;
                $attemptedQuestions = rand(60, 75);
                $correctAnswers = rand(20, $attemptedQuestions);
                $wrongAnswers = $attemptedQuestions - $correctAnswers;
                $unansweredQuestions = $totalQuestions - $attemptedQuestions;
                
                $score = ($correctAnswers * 4) - ($wrongAnswers * 1);
                $percentage = ($score / 300) * 100;
                $timeSpent = rand(5400, 10800); // 1.5 to 3 hours in seconds
                
                $now = date('Y-m-d H:i:s');
                
                // Insert submission
                $submissionStmt->execute([
                    $submissionId,
                    $exam['id'],
                    $user['id'],
                    $startTime->format('Y-m-d H:i:s'),
                    $endTime->format('Y-m-d H:i:s'),
                    'SUBMITTED',
                    $timeSpent,
                    $score,
                    $percentage,
                    $now,
                    $now
                ]);
                
                // Insert result
                $subjectWiseResults = json_encode([
                    'PHYSICS' => [
                        'attempted' => rand(18, 25),
                        'correct' => rand(6, 15),
                        'wrong' => rand(3, 10),
                        'score' => rand(20, 60)
                    ],
                    'CHEMISTRY' => [
                        'attempted' => rand(18, 25),
                        'correct' => rand(6, 15),
                        'wrong' => rand(3, 10),
                        'score' => rand(20, 60)
                    ],
                    'MATHEMATICS' => [
                        'attempted' => rand(18, 25),
                        'correct' => rand(6, 15),
                        'wrong' => rand(3, 10),
                        'score' => rand(20, 60)
                    ]
                ]);
                
                $timeAnalysis = json_encode([
                    'totalTime' => $timeSpent,
                    'averageTimePerQuestion' => $timeSpent / $totalQuestions,
                    'subjectWiseTime' => [
                        ['subject' => 'PHYSICS', 'timeSpent' => rand(1800, 3600)],
                        ['subject' => 'CHEMISTRY', 'timeSpent' => rand(1800, 3600)],
                        ['subject' => 'MATHEMATICS', 'timeSpent' => rand(1800, 3600)]
                    ]
                ]);
                
                $resultStmt->execute([
                    $resultId,
                    $exam['id'],
                    $user['id'],
                    $submissionId,
                    $totalQuestions,
                    $attemptedQuestions,
                    $correctAnswers,
                    $wrongAnswers,
                    $unansweredQuestions,
                    $score,
                    $percentage,
                    $rank++,
                    $subjectWiseResults,
                    $timeAnalysis,
                    $now
                ]);
                
                // Insert detailed submission to MongoDB
                $detailedSubmission = [
                    '_id' => new MongoDB\BSON\ObjectId($submissionId),
                    'examId' => $exam['id'],
                    'userId' => $user['id'],
                    'status' => 'SUBMITTED',
                    'startTime' => new MongoDB\BSON\UTCDateTime($startTime),
                    'endTime' => new MongoDB\BSON\UTCDateTime($endTime),
                    'timeSpent' => $timeSpent,
                    'answers' => $this->generateSampleAnswers($totalQuestions, $attemptedQuestions),
                    'flaggedQuestions' => array_rand(range(1, $totalQuestions), rand(0, 5)),
                    'visitedQuestions' => range(1, $attemptedQuestions),
                    'proctoring' => [
                        'violations' => rand(0, 3),
                        'tabSwitches' => rand(0, 2),
                        'faceDetectionFailures' => rand(0, 1),
                        'suspiciousActivity' => []
                    ],
                    'createdAt' => new MongoDB\BSON\UTCDateTime(),
                    'updatedAt' => new MongoDB\BSON\UTCDateTime()
                ];
                
                $submissionCollection->insertOne($detailedSubmission);
                $totalSubmissions++;
            }
        }
        
        $this->log("✅ Sample submissions seeded: " . number_format($totalSubmissions) . " submissions");
    }
    
    private function seedAnalyticsData(): void
    {
        $this->log("📈 Seeding analytics data...");
        
        // Cache some analytics data in Redis
        $analyticsData = [
            'total_students' => self::TOTAL_STUDENTS,
            'total_questions' => self::TOTAL_QUESTIONS,
            'total_exams' => self::TOTAL_SAMPLE_EXAMS,
            'active_sessions' => rand(100, 500),
            'avg_score' => rand(120, 180),
            'subject_performance' => [
                'PHYSICS' => rand(60, 80),
                'CHEMISTRY' => rand(65, 85),
                'MATHEMATICS' => rand(55, 75)
            ],
            'difficulty_stats' => [
                'EASY' => ['attempted' => rand(80, 95), 'success_rate' => rand(70, 90)],
                'MEDIUM' => ['attempted' => rand(60, 80), 'success_rate' => rand(40, 60)],
                'HARD' => ['attempted' => rand(30, 50), 'success_rate' => rand(15, 35)]
            ]
        ];
        
        $this->redis->setex('analytics:dashboard', 3600, json_encode($analyticsData));
        $this->redis->setex('analytics:last_updated', 3600, date('Y-m-d H:i:s'));
        
        $this->log("✅ Analytics data cached");
    }
    
    // Helper methods
    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    private function generateUniqueEmail(string $firstName, string $lastName, int $index): string
    {
        $baseEmail = strtolower($firstName . '.' . $lastName . '.' . $index);
        $domains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'student.edu'];
        return $baseEmail . '@' . $domains[array_rand($domains)];
    }
    
    private function generateQuestion(string $subject, string $topic, string $type): string
    {
        $templates = [
            'PHYSICS' => [
                "A particle moves with velocity {v} m/s. Calculate the {parameter}.",
                "In the given circuit, find the {parameter} when {condition}.",
                "A wave has frequency {f} Hz. What is its {parameter}?"
            ],
            'CHEMISTRY' => [
                "Calculate the {parameter} of {compound} in the given reaction.",
                "What is the {parameter} when {reactant} reacts with {product}?",
                "Find the {parameter} of the solution containing {concentration} M {compound}."
            ],
            'MATHEMATICS' => [
                "If f(x) = {function}, find {parameter}.",
                "In triangle ABC, if {condition}, find {parameter}.",
                "The probability of {event} is {probability}. Find {parameter}."
            ]
        ];
        
        $template = $templates[$subject][array_rand($templates[$subject])];
        
        // Replace placeholders with random values
        $replacements = [
            '{v}' => rand(10, 100),
            '{f}' => rand(100, 1000),
            '{parameter}' => ['acceleration', 'velocity', 'displacement', 'force'][array_rand(['acceleration', 'velocity', 'displacement', 'force'])],
            '{condition}' => 'the given condition',
            '{compound}' => ['NaCl', 'H2SO4', 'CaCO3', 'NH3'][array_rand(['NaCl', 'H2SO4', 'CaCO3', 'NH3'])],
            '{reactant}' => 'the reactant',
            '{product}' => 'the product',
            '{concentration}' => rand(1, 10),
            '{function}' => 'x² + 2x + 1',
            '{event}' => 'getting a head',
            '{probability}' => '0.5'
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
    
    private function generateOptions(string $type): array
    {
        switch ($type) {
            case 'SINGLE_CORRECT':
                return [
                    'A' => $this->faker->sentence(3),
                    'B' => $this->faker->sentence(3),
                    'C' => $this->faker->sentence(3),
                    'D' => $this->faker->sentence(3)
                ];
            case 'MULTIPLE_CORRECT':
                return [
                    'A' => $this->faker->sentence(3),
                    'B' => $this->faker->sentence(3),
                    'C' => $this->faker->sentence(3),
                    'D' => $this->faker->sentence(3)
                ];
            case 'NUMERICAL':
                return [];
            case 'ASSERTION_REASON':
                return [
                    'assertion' => $this->faker->sentence(8),
                    'reason' => $this->faker->sentence(8)
                ];
            default:
                return [];
        }
    }
    
    private function generateCorrectAnswer(string $type): mixed
    {
        switch ($type) {
            case 'SINGLE_CORRECT':
                return ['A', 'B', 'C', 'D'][array_rand(['A', 'B', 'C', 'D'])];
            case 'MULTIPLE_CORRECT':
                $options = ['A', 'B', 'C', 'D'];
                $count = rand(2, 3);
                return array_slice($options, 0, $count);
            case 'NUMERICAL':
                return $this->faker->randomFloat(2, 1, 100);
            case 'ASSERTION_REASON':
                return ['both_true_reason_correct', 'both_true_reason_incorrect', 'assertion_true_reason_false', 'both_false'][array_rand(['both_true_reason_correct', 'both_true_reason_incorrect', 'assertion_true_reason_false', 'both_false'])];
            default:
                return 'A';
        }
    }
    
    private function generateExplanation(string $subject, string $topic): string
    {
        return "This question tests the understanding of {$topic} in {$subject}. " . $this->faker->paragraph(2);
    }
    
    private function getMarksForDifficulty(string $difficulty): int
    {
        return match ($difficulty) {
            'EASY' => 2,
            'MEDIUM' => 3,
            'HARD' => 4,
            default => 3
        };
    }
    
    private function getNegativeMarks(string $difficulty): float
    {
        return match ($difficulty) {
            'EASY' => 0.5,
            'MEDIUM' => 1.0,
            'HARD' => 1.0,
            default => 1.0
        };
    }
    
    private function generateTags(string $subject, string $topic): array
    {
        $baseTags = [strtolower($subject), strtolower(str_replace(' ', '_', $topic))];
        $additionalTags = ['jee_main', 'jee_advanced', 'practice', 'important'];
        
        return array_merge($baseTags, array_slice($additionalTags, 0, rand(1, 3)));
    }
    
    private function generateExamTitle(string $type, int $index): string
    {
        $titles = [
            'JEE_MAIN' => "JEE Main Mock Test " . ($index + 1),
            'JEE_ADVANCED' => "JEE Advanced Practice Test " . ($index + 1),
            'PRACTICE' => "Practice Test - " . date('M Y'),
            'MOCK' => "Full Length Mock Test " . ($index + 1)
        ];
        
        return $titles[$type] ?? "Sample Test " . ($index + 1);
    }
    
    private function generateExamDescription(string $type): string
    {
        $descriptions = [
            'JEE_MAIN' => "Comprehensive JEE Main practice test covering Physics, Chemistry, and Mathematics with latest pattern and difficulty level.",
            'JEE_ADVANCED' => "Advanced level test designed to match JEE Advanced pattern with complex problem-solving questions.",
            'PRACTICE' => "Regular practice test to assess your preparation level and identify areas for improvement.",
            'MOCK' => "Full-length mock test simulating actual exam conditions for complete preparation assessment."
        ];
        
        return $descriptions[$type] ?? "Sample test for practice and assessment.";
    }
    
    private function getAdminUserId(): string
    {
        $result = $this->mysql->query("SELECT id FROM users WHERE role = 'SUPER_ADMIN' LIMIT 1")->fetch();
        return $result ? $result['id'] : $this->generateUuid();
    }
    
    private function generateSampleAnswers(int $totalQuestions, int $attempted): array
    {
        $answers = [];
        
        for ($i = 1; $i <= $attempted; $i++) {
            $answers[] = [
                'questionId' => (string)$i,
                'answer' => ['A', 'B', 'C', 'D'][array_rand(['A', 'B', 'C', 'D'])],
                'timeSpent' => rand(30, 300),
                'attemptNumber' => 1,
                'isMarkedForReview' => rand(0, 1) === 1,
                'timestamp' => time() - rand(0, 10800)
            ];
        }
        
        return $answers;
    }
    
    private function updateProgress(string $operation, float $progress, int $completed): void
    {
        $this->redis->hMSet('seeding:progress', [
            $operation => $progress,
            $operation . '_completed' => $completed,
            'last_updated' => time()
        ]);
    }
    
    private function displaySeedingStats(): void
    {
        $stats = [
            'Students' => number_format(self::TOTAL_STUDENTS),
            'Questions' => number_format(self::TOTAL_QUESTIONS),
            'Sample Exams' => number_format(self::TOTAL_SAMPLE_EXAMS),
            'Submissions' => 'Generated',
            'Analytics Data' => 'Cached'
        ];
        
        $this->log("\n📊 Seeding Statistics:");
        foreach ($stats as $item => $count) {
            $this->log("  • {$item}: {$count}");
        }
        
        $this->log("\n🎯 Next Steps:");
        $this->log("  1. Start the application: docker-compose up -d");
        $this->log("  2. Access admin panel: http://localhost:3001");
        $this->log("  3. Login with: admin@jeeportal.com / admin123");
        $this->log("  4. Test student portal: http://localhost:3000");
    }
    
    private function log(string $message): void
    {
        echo date('[Y-m-d H:i:s] ') . $message . PHP_EOL;
    }
}

// Main execution
if (php_sapi_name() === 'cli') {
    echo "🌱 JEE Portal Laravel/PHP Database Seeder\n";
    echo "==========================================\n\n";
    
    $seeder = new DatabaseSeeder();
    $seeder->seedAll();
} else {
    http_response_code(403);
    echo "This script can only be run from command line.";
}