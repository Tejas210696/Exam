<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use Exception;

class EvaluationService
{
    private Database $database;
    private ScoringService $scoringService;
    private RankingService $rankingService;
    private AnalyticsService $analyticsService;
    private Logger $logger;

    public function __construct(
        Database $database,
        ScoringService $scoringService,
        RankingService $rankingService,
        AnalyticsService $analyticsService,
        Logger $logger
    ) {
        $this->database = $database;
        $this->scoringService = $scoringService;
        $this->rankingService = $rankingService;
        $this->analyticsService = $analyticsService;
        $this->logger = $logger;
    }

    /**
     * Evaluate a single submission
     */
    public function evaluateSubmission(string $submissionId): array
    {
        try {
            $this->logger->info('Starting submission evaluation', ['submission_id' => $submissionId]);

            // Get submission data
            $submission = $this->getSubmission($submissionId);
            if (!$submission) {
                throw new Exception("Submission not found: {$submissionId}");
            }

            // Get exam data
            $exam = $this->getExam($submission['examId']);
            if (!$exam) {
                throw new Exception("Exam not found: {$submission['examId']}");
            }

            // Get questions for the exam
            $questions = $this->getExamQuestions($submission['examId']);
            if (empty($questions)) {
                throw new Exception("No questions found for exam: {$submission['examId']}");
            }

            // Calculate scores
            $scoringResult = $this->scoringService->calculateScore($submission, $questions, $exam);

            // Create exam result
            $result = [
                'id' => $this->generateId(),
                'examId' => $submission['examId'],
                'userId' => $submission['userId'],
                'submissionId' => $submissionId,
                'totalQuestions' => count($questions),
                'attemptedQuestions' => $scoringResult['attempted'],
                'correctAnswers' => $scoringResult['correct'],
                'wrongAnswers' => $scoringResult['wrong'],
                'unansweredQuestions' => $scoringResult['unanswered'],
                'score' => $scoringResult['score'],
                'percentage' => $scoringResult['percentage'],
                'subjectWiseResults' => $scoringResult['subjectWise'],
                'timeAnalysis' => $this->calculateTimeAnalysis($submission, $questions),
                'createdAt' => date('Y-m-d H:i:s'),
            ];

            // Save result to database
            $this->saveExamResult($result);

            // Update submission with score
            $this->updateSubmissionScore($submissionId, $scoringResult['score'], $scoringResult['percentage']);

            // Update question analytics
            $this->updateQuestionAnalytics($submission, $questions, $scoringResult);

            $this->logger->info('Submission evaluation completed', [
                'submission_id' => $submissionId,
                'score' => $scoringResult['score'],
                'percentage' => $scoringResult['percentage'],
            ]);

            return [
                'success' => true,
                'data' => $result,
            ];

        } catch (Exception $e) {
            $this->logger->error('Submission evaluation failed', [
                'submission_id' => $submissionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Evaluate all submissions for an exam and calculate rankings
     */
    public function evaluateExam(string $examId): array
    {
        try {
            $this->logger->info('Starting exam evaluation', ['exam_id' => $examId]);

            // Get all submissions for the exam
            $submissions = $this->getExamSubmissions($examId);
            if (empty($submissions)) {
                throw new Exception("No submissions found for exam: {$examId}");
            }

            $evaluatedCount = 0;
            $errors = [];

            // Evaluate each submission
            foreach ($submissions as $submission) {
                try {
                    $this->evaluateSubmission($submission['_id']);
                    $evaluatedCount++;
                } catch (Exception $e) {
                    $errors[] = [
                        'submission_id' => $submission['_id'],
                        'error' => $e->getMessage(),
                    ];
                    $this->logger->error('Individual submission evaluation failed', [
                        'submission_id' => $submission['_id'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Calculate rankings
            $rankingResult = $this->rankingService->calculateRankings($examId);

            // Generate exam analytics
            $analytics = $this->analyticsService->generateExamAnalytics($examId);

            $this->logger->info('Exam evaluation completed', [
                'exam_id' => $examId,
                'total_submissions' => count($submissions),
                'evaluated_count' => $evaluatedCount,
                'error_count' => count($errors),
            ]);

            return [
                'success' => true,
                'data' => [
                    'examId' => $examId,
                    'totalSubmissions' => count($submissions),
                    'evaluatedCount' => $evaluatedCount,
                    'errorCount' => count($errors),
                    'errors' => $errors,
                    'rankings' => $rankingResult,
                    'analytics' => $analytics,
                ],
            ];

        } catch (Exception $e) {
            $this->logger->error('Exam evaluation failed', [
                'exam_id' => $examId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Get evaluation results for a submission
     */
    public function getResults(string $submissionId): array
    {
        try {
            $mysql = $this->database->getMysql();
            
            $stmt = $mysql->prepare("
                SELECT r.*, e.title as exam_title, e.total_marks, e.passing_marks,
                       u.first_name, u.last_name, u.email
                FROM exam_results r
                JOIN exams e ON r.exam_id = e.id
                JOIN users u ON r.user_id = u.id
                WHERE r.submission_id = ?
            ");
            
            $stmt->execute([$submissionId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$result) {
                throw new Exception("Results not found for submission: {$submissionId}");
            }

            // Decode JSON fields
            $result['subject_wise_results'] = json_decode($result['subject_wise_results'] ?? '[]', true);
            $result['time_analysis'] = json_decode($result['time_analysis'] ?? '{}', true);

            return [
                'success' => true,
                'data' => $result,
            ];

        } catch (Exception $e) {
            $this->logger->error('Get results failed', [
                'submission_id' => $submissionId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get all results for an exam
     */
    public function getExamResults(string $examId, int $page = 1, int $limit = 50): array
    {
        try {
            $offset = ($page - 1) * $limit;
            $mysql = $this->database->getMysql();
            
            // Get total count
            $countStmt = $mysql->prepare("SELECT COUNT(*) FROM exam_results WHERE exam_id = ?");
            $countStmt->execute([$examId]);
            $totalCount = $countStmt->fetchColumn();
            
            // Get results
            $stmt = $mysql->prepare("
                SELECT r.*, u.first_name, u.last_name, u.email
                FROM exam_results r
                JOIN users u ON r.user_id = u.id
                WHERE r.exam_id = ?
                ORDER BY r.rank ASC, r.score DESC
                LIMIT ? OFFSET ?
            ");
            
            $stmt->execute([$examId, $limit, $offset]);
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Decode JSON fields
            foreach ($results as &$result) {
                $result['subject_wise_results'] = json_decode($result['subject_wise_results'] ?? '[]', true);
                $result['time_analysis'] = json_decode($result['time_analysis'] ?? '{}', true);
            }

            return [
                'success' => true,
                'data' => [
                    'results' => $results,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $totalCount,
                        'totalPages' => ceil($totalCount / $limit),
                        'hasNext' => $page < ceil($totalCount / $limit),
                        'hasPrev' => $page > 1,
                    ],
                ],
            ];

        } catch (Exception $e) {
            $this->logger->error('Get exam results failed', [
                'exam_id' => $examId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get submission from MongoDB
     */
    private function getSubmission(string $submissionId): ?array
    {
        $mongodb = $this->database->getMongodb();
        $collection = $mongodb->selectCollection('jee_portal', 'submissions');
        
        $submission = $collection->findOne(['_id' => new \MongoDB\BSON\ObjectId($submissionId)]);
        
        return $submission ? $submission->toArray() : null;
    }

    /**
     * Get exam from MySQL
     */
    private function getExam(string $examId): ?array
    {
        $mysql = $this->database->getMysql();
        $stmt = $mysql->prepare("SELECT * FROM exams WHERE id = ?");
        $stmt->execute([$examId]);
        
        $exam = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($exam) {
            $exam['settings'] = json_decode($exam['settings'] ?? '{}', true);
        }
        
        return $exam ?: null;
    }

    /**
     * Get questions for an exam from MongoDB
     */
    private function getExamQuestions(string $examId): array
    {
        // This is simplified - in reality, you'd have exam-question relationships
        $mongodb = $this->database->getMongodb();
        $collection = $mongodb->selectCollection('jee_portal', 'questions');
        
        // For demo purposes, get random questions - implement proper exam-question mapping
        $cursor = $collection->find([], ['limit' => 100]);
        
        return $cursor->toArray();
    }

    /**
     * Get all submissions for an exam
     */
    private function getExamSubmissions(string $examId): array
    {
        $mongodb = $this->database->getMongodb();
        $collection = $mongodb->selectCollection('jee_portal', 'submissions');
        
        $cursor = $collection->find(['examId' => $examId, 'status' => 'SUBMITTED']);
        
        return $cursor->toArray();
    }

    /**
     * Calculate time analysis for submission
     */
    private function calculateTimeAnalysis(array $submission, array $questions): array
    {
        $totalTime = $submission['timeSpent'] ?? 0;
        $totalQuestions = count($questions);
        $averageTimePerQuestion = $totalQuestions > 0 ? $totalTime / $totalQuestions : 0;

        // Subject-wise time analysis
        $subjectWiseTime = [];
        $subjectQuestionCount = [];
        
        foreach ($questions as $question) {
            $subject = $question['subject'];
            if (!isset($subjectWiseTime[$subject])) {
                $subjectWiseTime[$subject] = 0;
                $subjectQuestionCount[$subject] = 0;
            }
            $subjectQuestionCount[$subject]++;
        }

        // Calculate time spent per subject based on answers
        foreach ($submission['answers'] as $answer) {
            $questionId = $answer['questionId'];
            $timeSpent = $answer['timeSpent'] ?? 0;
            
            // Find question subject
            foreach ($questions as $question) {
                if ((string)$question['_id'] === $questionId) {
                    $subject = $question['subject'];
                    $subjectWiseTime[$subject] += $timeSpent;
                    break;
                }
            }
        }

        // Format subject-wise results
        $subjectTimeAnalysis = [];
        foreach ($subjectWiseTime as $subject => $timeSpent) {
            $questionCount = $subjectQuestionCount[$subject] ?? 1;
            $subjectTimeAnalysis[] = [
                'subject' => $subject,
                'timeSpent' => $timeSpent,
                'averageTimePerQuestion' => $timeSpent / $questionCount,
            ];
        }

        return [
            'totalTime' => $totalTime,
            'averageTimePerQuestion' => $averageTimePerQuestion,
            'subjectWiseTime' => $subjectTimeAnalysis,
        ];
    }

    /**
     * Save exam result to MySQL
     */
    private function saveExamResult(array $result): void
    {
        $mysql = $this->database->getMysql();
        
        $stmt = $mysql->prepare("
            INSERT INTO exam_results (
                id, exam_id, user_id, submission_id, total_questions, attempted_questions,
                correct_answers, wrong_answers, unanswered_questions, score, percentage,
                subject_wise_results, time_analysis, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                total_questions = VALUES(total_questions),
                attempted_questions = VALUES(attempted_questions),
                correct_answers = VALUES(correct_answers),
                wrong_answers = VALUES(wrong_answers),
                unanswered_questions = VALUES(unanswered_questions),
                score = VALUES(score),
                percentage = VALUES(percentage),
                subject_wise_results = VALUES(subject_wise_results),
                time_analysis = VALUES(time_analysis)
        ");
        
        $stmt->execute([
            $result['id'],
            $result['examId'],
            $result['userId'],
            $result['submissionId'],
            $result['totalQuestions'],
            $result['attemptedQuestions'],
            $result['correctAnswers'],
            $result['wrongAnswers'],
            $result['unansweredQuestions'],
            $result['score'],
            $result['percentage'],
            json_encode($result['subjectWiseResults']),
            json_encode($result['timeAnalysis']),
            $result['createdAt'],
        ]);
    }

    /**
     * Update submission with calculated score
     */
    private function updateSubmissionScore(string $submissionId, float $score, float $percentage): void
    {
        $mongodb = $this->database->getMongodb();
        $collection = $mongodb->selectCollection('jee_portal', 'submissions');
        
        $collection->updateOne(
            ['_id' => new \MongoDB\BSON\ObjectId($submissionId)],
            [
                '$set' => [
                    'score' => $score,
                    'percentage' => $percentage,
                    'evaluatedAt' => new \MongoDB\BSON\UTCDateTime(),
                ]
            ]
        );
    }

    /**
     * Update question analytics
     */
    private function updateQuestionAnalytics(array $submission, array $questions, array $scoringResult): void
    {
        $mongodb = $this->database->getMongodb();
        $collection = $mongodb->selectCollection('jee_portal', 'questions');
        
        foreach ($submission['answers'] as $answer) {
            $questionId = $answer['questionId'];
            $isCorrect = $scoringResult['questionResults'][$questionId]['correct'] ?? false;
            $timeSpent = $answer['timeSpent'] ?? 0;
            
            // Update question metadata
            $collection->updateOne(
                ['_id' => new \MongoDB\BSON\ObjectId($questionId)],
                [
                    '$inc' => [
                        'metadata.timesUsed' => 1,
                        'metadata.totalTimeSpent' => $timeSpent,
                        'metadata.correctAttempts' => $isCorrect ? 1 : 0,
                        'metadata.totalAttempts' => 1,
                    ],
                    '$set' => [
                        'metadata.lastUsed' => new \MongoDB\BSON\UTCDateTime(),
                    ]
                ]
            );
        }
    }

    /**
     * Generate unique ID
     */
    private function generateId(): string
    {
        return bin2hex(random_bytes(16));
    }
}