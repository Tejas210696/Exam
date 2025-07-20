<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use Exception;

class FaceDetectionService
{
    private Logger $logger;
    private array $config;

    // Face detection thresholds
    private const FACE_CONFIDENCE_THRESHOLD = 0.7;
    private const MULTIPLE_FACES_THRESHOLD = 2;
    private const FACE_SIZE_MIN_THRESHOLD = 0.1; // 10% of image
    private const FACE_SIZE_MAX_THRESHOLD = 0.8; // 80% of image

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        $this->config = [
            'face_api_models_path' => '/app/models/',
            'opencv_cascade_path' => '/app/cascades/',
            'detection_interval' => 5, // seconds
            'violation_threshold' => 3, // consecutive violations
        ];
    }

    /**
     * Detect faces in image data
     */
    public function detectFaces(string $imageData, array $options = []): array
    {
        try {
            $this->logger->info('Starting face detection');

            // Decode base64 image
            $imageContent = base64_decode($imageData);
            if ($imageContent === false) {
                throw new Exception('Invalid image data format');
            }

            // Create temporary file for processing
            $tempFile = tempnam(sys_get_temp_dir(), 'face_detection_');
            file_put_contents($tempFile, $imageContent);

            // Detect faces using multiple methods for accuracy
            $faces = $this->detectFacesMultiMethod($tempFile, $options);

            // Clean up
            unlink($tempFile);

            $this->logger->info('Face detection completed', [
                'faces_detected' => count($faces),
            ]);

            return [
                'success' => true,
                'faces' => $faces,
                'metadata' => [
                    'detection_method' => 'multi-method',
                    'confidence_threshold' => self::FACE_CONFIDENCE_THRESHOLD,
                    'processing_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
                ],
            ];

        } catch (Exception $e) {
            $this->logger->error('Face detection failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'faces' => [],
            ];
        }
    }

    /**
     * Verify if detected face matches reference face
     */
    public function verifyFace(string $currentImageData, string $referenceImageData): array
    {
        try {
            $this->logger->info('Starting face verification');

            // Detect faces in both images
            $currentFaces = $this->detectFaces($currentImageData);
            $referenceFaces = $this->detectFaces($referenceImageData);

            if (!$currentFaces['success'] || !$referenceFaces['success']) {
                throw new Exception('Failed to detect faces in one or both images');
            }

            if (empty($currentFaces['faces']) || empty($referenceFaces['faces'])) {
                return [
                    'success' => true,
                    'verified' => false,
                    'confidence' => 0.0,
                    'reason' => 'No faces detected in one or both images',
                ];
            }

            // Get the most prominent face from each image
            $currentFace = $this->getProminentFace($currentFaces['faces']);
            $referenceFace = $this->getProminentFace($referenceFaces['faces']);

            // Calculate similarity
            $similarity = $this->calculateFaceSimilarity($currentFace, $referenceFace);

            $verified = $similarity >= self::FACE_CONFIDENCE_THRESHOLD;

            $this->logger->info('Face verification completed', [
                'verified' => $verified,
                'similarity' => $similarity,
            ]);

            return [
                'success' => true,
                'verified' => $verified,
                'confidence' => $similarity,
                'current_face' => $currentFace,
                'reference_face' => $referenceFace,
                'threshold' => self::FACE_CONFIDENCE_THRESHOLD,
            ];

        } catch (Exception $e) {
            $this->logger->error('Face verification failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'verified' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Analyze face for various attributes and violations
     */
    public function analyzeFace(string $imageData, array $context = []): array
    {
        try {
            $this->logger->info('Starting face analysis');

            $faces = $this->detectFaces($imageData);
            
            if (!$faces['success'] || empty($faces['faces'])) {
                return [
                    'success' => true,
                    'analysis' => [
                        'face_count' => 0,
                        'violations' => ['no_face_detected'],
                        'confidence' => 0.0,
                    ],
                ];
            }

            $faceCount = count($faces['faces']);
            $prominentFace = $this->getProminentFace($faces['faces']);
            
            // Analyze for violations
            $violations = $this->detectFaceViolations($faces['faces'], $context);
            
            // Analyze facial attributes
            $attributes = $this->analyzeFacialAttributes($prominentFace);
            
            // Calculate overall confidence
            $overallConfidence = $this->calculateOverallConfidence($faces['faces'], $violations);

            $analysis = [
                'face_count' => $faceCount,
                'prominent_face' => $prominentFace,
                'violations' => $violations,
                'attributes' => $attributes,
                'confidence' => $overallConfidence,
                'timestamp' => time(),
            ];

            $this->logger->info('Face analysis completed', [
                'face_count' => $faceCount,
                'violations' => count($violations),
                'confidence' => $overallConfidence,
            ]);

            return [
                'success' => true,
                'analysis' => $analysis,
            ];

        } catch (Exception $e) {
            $this->logger->error('Face analysis failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Detect faces using multiple methods for better accuracy
     */
    private function detectFacesMultiMethod(string $imagePath, array $options): array
    {
        $faces = [];

        // Method 1: OpenCV Haar Cascades (fast, basic)
        $haarFaces = $this->detectFacesHaar($imagePath);
        
        // Method 2: DNN-based detection (slower, more accurate)
        $dnnFaces = $this->detectFacesDNN($imagePath);
        
        // Method 3: Simple template matching (fallback)
        $templateFaces = $this->detectFacesTemplate($imagePath);

        // Combine results and remove duplicates
        $allFaces = array_merge($haarFaces, $dnnFaces, $templateFaces);
        $faces = $this->removeDuplicateFaces($allFaces);

        // Filter by confidence
        $faces = array_filter($faces, function($face) {
            return $face['confidence'] >= self::FACE_CONFIDENCE_THRESHOLD;
        });

        return array_values($faces);
    }

    /**
     * Detect faces using Haar Cascades (OpenCV simulation)
     */
    private function detectFacesHaar(string $imagePath): array
    {
        // Simulate Haar cascade detection
        // In production, this would use actual OpenCV bindings
        $imageInfo = getimagesize($imagePath);
        if (!$imageInfo) {
            return [];
        }

        // Simulate face detection results
        return [
            [
                'method' => 'haar',
                'x' => 100,
                'y' => 80,
                'width' => 150,
                'height' => 180,
                'confidence' => 0.85,
                'landmarks' => $this->generateSimulatedLandmarks(),
            ]
        ];
    }

    /**
     * Detect faces using DNN (Deep Neural Network simulation)
     */
    private function detectFacesDNN(string $imagePath): array
    {
        // Simulate DNN-based face detection
        // In production, this would use TensorFlow or similar
        $imageInfo = getimagesize($imagePath);
        if (!$imageInfo) {
            return [];
        }

        // Simulate more accurate detection
        return [
            [
                'method' => 'dnn',
                'x' => 105,
                'y' => 85,
                'width' => 145,
                'height' => 175,
                'confidence' => 0.92,
                'landmarks' => $this->generateSimulatedLandmarks(),
            ]
        ];
    }

    /**
     * Detect faces using template matching (basic fallback)
     */
    private function detectFacesTemplate(string $imagePath): array
    {
        // Simple template matching simulation
        $imageInfo = getimagesize($imagePath);
        if (!$imageInfo) {
            return [];
        }

        return [
            [
                'method' => 'template',
                'x' => 95,
                'y' => 75,
                'width' => 160,
                'height' => 190,
                'confidence' => 0.75,
                'landmarks' => $this->generateSimulatedLandmarks(),
            ]
        ];
    }

    /**
     * Remove duplicate faces from detection results
     */
    private function removeDuplicateFaces(array $faces): array
    {
        $unique = [];
        $overlapThreshold = 0.5;

        foreach ($faces as $face) {
            $isDuplicate = false;
            
            foreach ($unique as $uniqueFace) {
                $overlap = $this->calculateBoundingBoxOverlap($face, $uniqueFace);
                if ($overlap > $overlapThreshold) {
                    $isDuplicate = true;
                    // Keep the one with higher confidence
                    if ($face['confidence'] > $uniqueFace['confidence']) {
                        // Replace the existing one
                        $key = array_search($uniqueFace, $unique);
                        $unique[$key] = $face;
                    }
                    break;
                }
            }
            
            if (!$isDuplicate) {
                $unique[] = $face;
            }
        }

        return $unique;
    }

    /**
     * Calculate bounding box overlap
     */
    private function calculateBoundingBoxOverlap(array $face1, array $face2): float
    {
        $x1 = max($face1['x'], $face2['x']);
        $y1 = max($face1['y'], $face2['y']);
        $x2 = min($face1['x'] + $face1['width'], $face2['x'] + $face2['width']);
        $y2 = min($face1['y'] + $face1['height'], $face2['y'] + $face2['height']);

        if ($x2 <= $x1 || $y2 <= $y1) {
            return 0.0;
        }

        $intersection = ($x2 - $x1) * ($y2 - $y1);
        $area1 = $face1['width'] * $face1['height'];
        $area2 = $face2['width'] * $face2['height'];
        $union = $area1 + $area2 - $intersection;

        return $union > 0 ? $intersection / $union : 0.0;
    }

    /**
     * Get the most prominent face (largest, highest confidence)
     */
    private function getProminentFace(array $faces): array
    {
        if (empty($faces)) {
            return [];
        }

        usort($faces, function($a, $b) {
            $aScore = $a['confidence'] * ($a['width'] * $a['height']);
            $bScore = $b['confidence'] * ($b['width'] * $b['height']);
            return $bScore <=> $aScore;
        });

        return $faces[0];
    }

    /**
     * Calculate face similarity (simplified)
     */
    private function calculateFaceSimilarity(array $face1, array $face2): float
    {
        if (empty($face1) || empty($face2)) {
            return 0.0;
        }

        // Simplified similarity based on landmarks and face dimensions
        $dimensionSimilarity = $this->calculateDimensionSimilarity($face1, $face2);
        $landmarkSimilarity = $this->calculateLandmarkSimilarity($face1['landmarks'] ?? [], $face2['landmarks'] ?? []);
        
        // Weighted average
        return ($dimensionSimilarity * 0.3) + ($landmarkSimilarity * 0.7);
    }

    /**
     * Calculate dimension similarity
     */
    private function calculateDimensionSimilarity(array $face1, array $face2): float
    {
        $widthRatio = min($face1['width'], $face2['width']) / max($face1['width'], $face2['width']);
        $heightRatio = min($face1['height'], $face2['height']) / max($face1['height'], $face2['height']);
        
        return ($widthRatio + $heightRatio) / 2;
    }

    /**
     * Calculate landmark similarity
     */
    private function calculateLandmarkSimilarity(array $landmarks1, array $landmarks2): float
    {
        if (empty($landmarks1) || empty($landmarks2)) {
            return 0.5; // Default similarity when landmarks not available
        }

        // Simplified landmark comparison
        $totalDistance = 0;
        $count = min(count($landmarks1), count($landmarks2));
        
        for ($i = 0; $i < $count; $i++) {
            $distance = sqrt(
                pow($landmarks1[$i]['x'] - $landmarks2[$i]['x'], 2) +
                pow($landmarks1[$i]['y'] - $landmarks2[$i]['y'], 2)
            );
            $totalDistance += $distance;
        }

        $avgDistance = $count > 0 ? $totalDistance / $count : 100;
        
        // Convert distance to similarity (0-1 scale)
        return max(0, 1 - ($avgDistance / 100));
    }

    /**
     * Detect face-related violations
     */
    private function detectFaceViolations(array $faces, array $context): array
    {
        $violations = [];

        // No face detected
        if (empty($faces)) {
            $violations[] = 'no_face_detected';
            return $violations;
        }

        // Multiple faces detected
        if (count($faces) > self::MULTIPLE_FACES_THRESHOLD) {
            $violations[] = 'multiple_faces_detected';
        }

        $prominentFace = $this->getProminentFace($faces);

        // Face too small (too far from camera)
        $faceArea = $prominentFace['width'] * $prominentFace['height'];
        $imageArea = ($context['image_width'] ?? 640) * ($context['image_height'] ?? 480);
        $faceRatio = $faceArea / $imageArea;

        if ($faceRatio < self::FACE_SIZE_MIN_THRESHOLD) {
            $violations[] = 'face_too_small';
        }

        if ($faceRatio > self::FACE_SIZE_MAX_THRESHOLD) {
            $violations[] = 'face_too_large';
        }

        // Low confidence detection
        if ($prominentFace['confidence'] < self::FACE_CONFIDENCE_THRESHOLD) {
            $violations[] = 'low_confidence_detection';
        }

        return $violations;
    }

    /**
     * Analyze facial attributes
     */
    private function analyzeFacialAttributes(array $face): array
    {
        if (empty($face)) {
            return [];
        }

        // Simulate facial attribute analysis
        return [
            'estimated_age' => rand(18, 25),
            'estimated_gender' => rand(0, 1) ? 'male' : 'female',
            'emotion' => $this->detectEmotion($face),
            'head_pose' => $this->estimateHeadPose($face),
            'eye_gaze' => $this->estimateEyeGaze($face),
            'mouth_state' => $this->analyzeMouthState($face),
        ];
    }

    /**
     * Detect emotion (simplified)
     */
    private function detectEmotion(array $face): string
    {
        $emotions = ['neutral', 'focused', 'confused', 'stressed', 'calm'];
        return $emotions[array_rand($emotions)];
    }

    /**
     * Estimate head pose
     */
    private function estimateHeadPose(array $face): array
    {
        return [
            'yaw' => rand(-30, 30),
            'pitch' => rand(-20, 20),
            'roll' => rand(-15, 15),
        ];
    }

    /**
     * Estimate eye gaze direction
     */
    private function estimateEyeGaze(array $face): array
    {
        return [
            'direction' => ['center', 'left', 'right', 'up', 'down'][array_rand(['center', 'left', 'right', 'up', 'down'])],
            'confidence' => rand(70, 95) / 100,
        ];
    }

    /**
     * Analyze mouth state
     */
    private function analyzeMouthState(array $face): string
    {
        return ['closed', 'slightly_open', 'speaking'][array_rand(['closed', 'slightly_open', 'speaking'])];
    }

    /**
     * Calculate overall confidence score
     */
    private function calculateOverallConfidence(array $faces, array $violations): float
    {
        if (empty($faces)) {
            return 0.0;
        }

        $prominentFace = $this->getProminentFace($faces);
        $baseConfidence = $prominentFace['confidence'];

        // Reduce confidence based on violations
        $violationPenalty = count($violations) * 0.1;
        $adjustedConfidence = max(0.0, $baseConfidence - $violationPenalty);

        return round($adjustedConfidence, 2);
    }

    /**
     * Generate simulated facial landmarks
     */
    private function generateSimulatedLandmarks(): array
    {
        $landmarks = [];
        
        // Simulate 68 facial landmarks
        for ($i = 0; $i < 68; $i++) {
            $landmarks[] = [
                'x' => rand(100, 250),
                'y' => rand(80, 260),
                'confidence' => rand(80, 95) / 100,
            ];
        }

        return $landmarks;
    }
}