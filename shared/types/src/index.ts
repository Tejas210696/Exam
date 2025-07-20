// User Types
export interface User {
  id: string;
  email: string;
  firstName: string;
  lastName: string;
  role: UserRole;
  status: UserStatus;
  createdAt: Date;
  updatedAt: Date;
  lastLogin?: Date;
  profile?: UserProfile;
}

export enum UserRole {
  STUDENT = 'STUDENT',
  ADMIN = 'ADMIN',
  PROCTOR = 'PROCTOR',
  SUPER_ADMIN = 'SUPER_ADMIN',
}

export enum UserStatus {
  ACTIVE = 'ACTIVE',
  INACTIVE = 'INACTIVE',
  SUSPENDED = 'SUSPENDED',
  PENDING = 'PENDING',
}

export interface UserProfile {
  phone?: string;
  dateOfBirth?: Date;
  address?: Address;
  profileImage?: string;
  preferences?: UserPreferences;
}

export interface Address {
  street: string;
  city: string;
  state: string;
  country: string;
  zipCode: string;
}

export interface UserPreferences {
  language: string;
  timezone: string;
  notifications: NotificationPreferences;
}

export interface NotificationPreferences {
  email: boolean;
  sms: boolean;
  push: boolean;
}

// Exam Types
export interface Exam {
  id: string;
  title: string;
  description: string;
  type: ExamType;
  status: ExamStatus;
  duration: number; // in minutes
  totalMarks: number;
  passingMarks: number;
  startTime: Date;
  endTime: Date;
  instructions: string[];
  settings: ExamSettings;
  createdBy: string;
  createdAt: Date;
  updatedAt: Date;
}

export enum ExamType {
  JEE_MAIN = 'JEE_MAIN',
  JEE_ADVANCED = 'JEE_ADVANCED',
  NEET = 'NEET',
  PRACTICE = 'PRACTICE',
  MOCK = 'MOCK',
  CUSTOM = 'CUSTOM',
}

export enum ExamStatus {
  DRAFT = 'DRAFT',
  SCHEDULED = 'SCHEDULED',
  ACTIVE = 'ACTIVE',
  COMPLETED = 'COMPLETED',
  CANCELLED = 'CANCELLED',
}

export interface ExamSettings {
  shuffleQuestions: boolean;
  shuffleOptions: boolean;
  allowReview: boolean;
  showResult: boolean;
  negativeMarking: boolean;
  negativeMarkingRatio: number;
  proctoring: ProctoringSettings;
  security: SecuritySettings;
}

export interface ProctoringSettings {
  enabled: boolean;
  faceDetection: boolean;
  screenRecording: boolean;
  audioRecording: boolean;
  tabSwitchDetection: boolean;
  fullScreenRequired: boolean;
  violationThreshold: number;
}

export interface SecuritySettings {
  browserLockdown: boolean;
  disableCopyPaste: boolean;
  disableRightClick: boolean;
  disableF12: boolean;
  preventScreenshot: boolean;
  watermark: boolean;
}

// Question Types
export interface Question {
  id: string;
  type: QuestionType;
  subject: Subject;
  topic: string;
  difficulty: DifficultyLevel;
  question: string;
  options?: QuestionOption[];
  correctAnswer: string | string[];
  explanation?: string;
  marks: number;
  negativeMarks?: number;
  timeLimit?: number;
  tags: string[];
  createdBy: string;
  createdAt: Date;
  updatedAt: Date;
  metadata?: QuestionMetadata;
}

export enum QuestionType {
  SINGLE_CHOICE = 'SINGLE_CHOICE',
  MULTIPLE_CHOICE = 'MULTIPLE_CHOICE',
  NUMERICAL = 'NUMERICAL',
  TRUE_FALSE = 'TRUE_FALSE',
  FILL_BLANK = 'FILL_BLANK',
  ESSAY = 'ESSAY',
}

export enum Subject {
  MATHEMATICS = 'MATHEMATICS',
  PHYSICS = 'PHYSICS',
  CHEMISTRY = 'CHEMISTRY',
  BIOLOGY = 'BIOLOGY',
  ENGLISH = 'ENGLISH',
  GENERAL_KNOWLEDGE = 'GENERAL_KNOWLEDGE',
}

export enum DifficultyLevel {
  EASY = 'EASY',
  MEDIUM = 'MEDIUM',
  HARD = 'HARD',
  EXPERT = 'EXPERT',
}

export interface QuestionOption {
  id: string;
  text: string;
  isCorrect?: boolean;
}

export interface QuestionMetadata {
  averageTime?: number;
  successRate?: number;
  timesUsed?: number;
  lastUsed?: Date;
}

// Submission Types
export interface ExamSubmission {
  id: string;
  examId: string;
  userId: string;
  startTime: Date;
  endTime?: Date;
  status: SubmissionStatus;
  answers: Answer[];
  timeSpent: number;
  score?: number;
  percentage?: number;
  rank?: number;
  violations: ProctoringViolation[];
  metadata: SubmissionMetadata;
  createdAt: Date;
  updatedAt: Date;
}

export enum SubmissionStatus {
  IN_PROGRESS = 'IN_PROGRESS',
  SUBMITTED = 'SUBMITTED',
  AUTO_SUBMITTED = 'AUTO_SUBMITTED',
  DISQUALIFIED = 'DISQUALIFIED',
}

export interface Answer {
  questionId: string;
  selectedOptions?: string[];
  textAnswer?: string;
  numericalAnswer?: number;
  timeSpent: number;
  isMarkedForReview: boolean;
  isAnswered: boolean;
  visitCount: number;
}

export interface ProctoringViolation {
  id: string;
  type: ViolationType;
  timestamp: Date;
  severity: ViolationSeverity;
  description: string;
  evidence?: string; // URL to screenshot/video
  resolved: boolean;
}

export enum ViolationType {
  FACE_NOT_DETECTED = 'FACE_NOT_DETECTED',
  MULTIPLE_FACES = 'MULTIPLE_FACES',
  TAB_SWITCH = 'TAB_SWITCH',
  FULL_SCREEN_EXIT = 'FULL_SCREEN_EXIT',
  SUSPICIOUS_ACTIVITY = 'SUSPICIOUS_ACTIVITY',
  AUDIO_ANOMALY = 'AUDIO_ANOMALY',
}

export enum ViolationSeverity {
  LOW = 'LOW',
  MEDIUM = 'MEDIUM',
  HIGH = 'HIGH',
  CRITICAL = 'CRITICAL',
}

export interface SubmissionMetadata {
  browserInfo: BrowserInfo;
  deviceInfo: DeviceInfo;
  ipAddress: string;
  location?: Location;
}

export interface BrowserInfo {
  name: string;
  version: string;
  userAgent: string;
}

export interface DeviceInfo {
  type: string;
  os: string;
  screenResolution: string;
}

export interface Location {
  latitude: number;
  longitude: number;
  city?: string;
  country?: string;
}

// Result Types
export interface ExamResult {
  id: string;
  examId: string;
  userId: string;
  submissionId: string;
  totalQuestions: number;
  attemptedQuestions: number;
  correctAnswers: number;
  wrongAnswers: number;
  unansweredQuestions: number;
  score: number;
  percentage: number;
  rank: number;
  totalCandidates: number;
  subjectWiseResults: SubjectResult[];
  timeAnalysis: TimeAnalysis;
  createdAt: Date;
}

export interface SubjectResult {
  subject: Subject;
  totalQuestions: number;
  attemptedQuestions: number;
  correctAnswers: number;
  score: number;
  percentage: number;
}

export interface TimeAnalysis {
  totalTime: number;
  averageTimePerQuestion: number;
  subjectWiseTime: SubjectTimeAnalysis[];
}

export interface SubjectTimeAnalysis {
  subject: Subject;
  timeSpent: number;
  averageTimePerQuestion: number;
}

// Analytics Types
export interface ExamAnalytics {
  examId: string;
  totalCandidates: number;
  averageScore: number;
  highestScore: number;
  lowestScore: number;
  passPercentage: number;
  averageTime: number;
  questionAnalytics: QuestionAnalytics[];
  subjectAnalytics: SubjectAnalytics[];
  difficultyAnalytics: DifficultyAnalytics[];
  timeDistribution: TimeDistribution;
}

export interface QuestionAnalytics {
  questionId: string;
  attempted: number;
  correct: number;
  wrong: number;
  skipped: number;
  averageTime: number;
  difficultyIndex: number;
  discriminationIndex: number;
}

export interface SubjectAnalytics {
  subject: Subject;
  averageScore: number;
  averageTime: number;
  totalQuestions: number;
}

export interface DifficultyAnalytics {
  level: DifficultyLevel;
  averageScore: number;
  averageTime: number;
  totalQuestions: number;
}

export interface TimeDistribution {
  intervals: TimeInterval[];
}

export interface TimeInterval {
  range: string;
  count: number;
}

// API Response Types
export interface ApiResponse<T = any> {
  success: boolean;
  data?: T;
  message?: string;
  errors?: string[];
  pagination?: PaginationInfo;
}

export interface PaginationInfo {
  page: number;
  limit: number;
  total: number;
  totalPages: number;
  hasNext: boolean;
  hasPrev: boolean;
}

// Authentication Types
export interface AuthTokens {
  accessToken: string;
  refreshToken: string;
  expiresIn: number;
}

export interface LoginRequest {
  email: string;
  password: string;
  rememberMe?: boolean;
}

export interface RegisterRequest {
  email: string;
  password: string;
  firstName: string;
  lastName: string;
  role: UserRole;
}

export interface JwtPayload {
  sub: string;
  email: string;
  role: UserRole;
  iat: number;
  exp: number;
}

// WebSocket Types
export interface WebSocketMessage {
  type: MessageType;
  payload: any;
  timestamp: Date;
}

export enum MessageType {
  EXAM_START = 'EXAM_START',
  EXAM_END = 'EXAM_END',
  ANSWER_SAVE = 'ANSWER_SAVE',
  TIME_UPDATE = 'TIME_UPDATE',
  VIOLATION_ALERT = 'VIOLATION_ALERT',
  PROCTOR_MESSAGE = 'PROCTOR_MESSAGE',
  SYSTEM_MESSAGE = 'SYSTEM_MESSAGE',
}

// Configuration Types
export interface DatabaseConfig {
  host: string;
  port: number;
  database: string;
  username: string;
  password: string;
  ssl?: boolean;
}

export interface RedisConfig {
  host: string;
  port: number;
  password?: string;
  db?: number;
}

export interface JwtConfig {
  secret: string;
  expiresIn: string;
  refreshExpiresIn: string;
}

// Error Types
export class AppError extends Error {
  public statusCode: number;
  public isOperational: boolean;

  constructor(message: string, statusCode: number, isOperational: boolean = true) {
    super(message);
    this.statusCode = statusCode;
    this.isOperational = isOperational;

    Error.captureStackTrace(this, this.constructor);
  }
}

export enum ErrorCodes {
  VALIDATION_ERROR = 'VALIDATION_ERROR',
  AUTHENTICATION_ERROR = 'AUTHENTICATION_ERROR',
  AUTHORIZATION_ERROR = 'AUTHORIZATION_ERROR',
  NOT_FOUND_ERROR = 'NOT_FOUND_ERROR',
  DUPLICATE_ERROR = 'DUPLICATE_ERROR',
  INTERNAL_SERVER_ERROR = 'INTERNAL_SERVER_ERROR',
  EXAM_NOT_ACTIVE = 'EXAM_NOT_ACTIVE',
  EXAM_ALREADY_SUBMITTED = 'EXAM_ALREADY_SUBMITTED',
  EXAM_TIME_EXPIRED = 'EXAM_TIME_EXPIRED',
  PROCTOR_VIOLATION = 'PROCTOR_VIOLATION',
}

// Utility Types
export type Nullable<T> = T | null;
export type Optional<T> = T | undefined;
export type DeepPartial<T> = {
  [P in keyof T]?: T[P] extends object ? DeepPartial<T[P]> : T[P];
};

// Event Types
export interface DomainEvent {
  id: string;
  type: string;
  aggregateId: string;
  payload: any;
  timestamp: Date;
  version: number;
}

export interface UserRegisteredEvent extends DomainEvent {
  type: 'USER_REGISTERED';
  payload: {
    userId: string;
    email: string;
    role: UserRole;
  };
}

export interface ExamStartedEvent extends DomainEvent {
  type: 'EXAM_STARTED';
  payload: {
    examId: string;
    userId: string;
    startTime: Date;
  };
}

export interface ExamSubmittedEvent extends DomainEvent {
  type: 'EXAM_SUBMITTED';
  payload: {
    examId: string;
    userId: string;
    submissionId: string;
    endTime: Date;
  };
}

export interface ViolationDetectedEvent extends DomainEvent {
  type: 'VIOLATION_DETECTED';
  payload: {
    examId: string;
    userId: string;
    violation: ProctoringViolation;
  };
}