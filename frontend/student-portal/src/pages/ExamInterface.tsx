import React, { useState, useEffect, useCallback, useRef } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  Box,
  Paper,
  Typography,
  Button,
  LinearProgress,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Alert,
  Chip,
  Grid,
  Card,
  CardContent,
  Radio,
  RadioGroup,
  FormControlLabel,
  FormControl,
  TextField,
  Checkbox,
  FormGroup,
} from '@mui/material';
import {
  Timer,
  Visibility,
  VisibilityOff,
  Flag,
  NavigateNext,
  NavigateBefore,
  Send,
} from '@mui/icons-material';
import { useHotkeys } from 'react-hotkeys-hook';
import Webcam from 'react-webcam';

import { useAppDispatch, useAppSelector } from '../hooks/redux';
import { 
  startExam, 
  submitAnswer, 
  submitExam,
  markForReview,
  nextQuestion,
  previousQuestion,
  goToQuestion 
} from '../store/slices/examSlice';
import { ProctoringMonitor } from '../components/ProctoringMonitor';
import { ExamTimer } from '../components/ExamTimer';
import { QuestionPalette } from '../components/QuestionPalette';
import { Question, Answer } from '@jee-portal/types';

const ExamInterface: React.FC = () => {
  const { examId } = useParams<{ examId: string }>();
  const navigate = useNavigate();
  const dispatch = useAppDispatch();
  
  const {
    currentExam,
    questions,
    currentQuestionIndex,
    answers,
    timeRemaining,
    isSubmitting,
    violations,
  } = useAppSelector((state) => state.exam);

  const [selectedAnswer, setSelectedAnswer] = useState<string | string[]>('');
  const [textAnswer, setTextAnswer] = useState('');
  const [numericalAnswer, setNumericalAnswer] = useState<number | ''>('');
  const [showSubmitDialog, setShowSubmitDialog] = useState(false);
  const [isFullscreen, setIsFullscreen] = useState(false);
  const [proctoringEnabled, setProctoringEnabled] = useState(true);
  
  const webcamRef = useRef<Webcam>(null);
  const questionStartTime = useRef<Date>(new Date());

  const currentQuestion = questions[currentQuestionIndex];

  // Initialize exam
  useEffect(() => {
    if (examId) {
      dispatch(startExam(examId));
      enterFullscreen();
    }
  }, [examId, dispatch]);

  // Fullscreen management
  const enterFullscreen = useCallback(() => {
    if (document.documentElement.requestFullscreen) {
      document.documentElement.requestFullscreen();
      setIsFullscreen(true);
    }
  }, []);

  const exitFullscreen = useCallback(() => {
    if (document.exitFullscreen) {
      document.exitFullscreen();
      setIsFullscreen(false);
    }
  }, []);

  // Handle fullscreen change
  useEffect(() => {
    const handleFullscreenChange = () => {
      setIsFullscreen(!!document.fullscreenElement);
      if (!document.fullscreenElement && currentExam?.settings.security.fullScreenRequired) {
        // Log violation
        console.warn('Fullscreen exited - potential violation');
      }
    };

    document.addEventListener('fullscreenchange', handleFullscreenChange);
    return () => document.removeEventListener('fullscreenchange', handleFullscreenChange);
  }, [currentExam]);

  // Keyboard shortcuts
  useHotkeys('ctrl+s', (e) => {
    e.preventDefault();
    handleSaveAnswer();
  });

  useHotkeys('ctrl+m', (e) => {
    e.preventDefault();
    handleMarkForReview();
  });

  useHotkeys('ctrl+n', () => handleNextQuestion());
  useHotkeys('ctrl+p', () => handlePreviousQuestion());

  // Prevent context menu and key combinations
  useEffect(() => {
    const preventRightClick = (e: MouseEvent) => {
      if (currentExam?.settings.security.disableRightClick) {
        e.preventDefault();
      }
    };

    const preventKeyShortcuts = (e: KeyboardEvent) => {
      if (currentExam?.settings.security.disableF12 && e.key === 'F12') {
        e.preventDefault();
      }
      if (currentExam?.settings.security.disableCopyPaste) {
        if ((e.ctrlKey || e.metaKey) && (e.key === 'c' || e.key === 'v' || e.key === 'x')) {
          e.preventDefault();
        }
      }
    };

    document.addEventListener('contextmenu', preventRightClick);
    document.addEventListener('keydown', preventKeyShortcuts);

    return () => {
      document.removeEventListener('contextmenu', preventRightClick);
      document.removeEventListener('keydown', preventKeyShortcuts);
    };
  }, [currentExam]);

  const handleAnswerChange = (value: string | string[]) => {
    setSelectedAnswer(value);
  };

  const handleSaveAnswer = () => {
    if (!currentQuestion) return;

    const timeSpent = Math.floor((new Date().getTime() - questionStartTime.current.getTime()) / 1000);
    
    const answer: Partial<Answer> = {
      questionId: currentQuestion.id,
      timeSpent,
      isAnswered: true,
      visitCount: (answers[currentQuestion.id]?.visitCount || 0) + 1,
    };

    switch (currentQuestion.type) {
      case 'SINGLE_CHOICE':
        answer.selectedOptions = [selectedAnswer as string];
        break;
      case 'MULTIPLE_CHOICE':
        answer.selectedOptions = selectedAnswer as string[];
        break;
      case 'NUMERICAL':
        answer.numericalAnswer = numericalAnswer as number;
        break;
      case 'FILL_BLANK':
      case 'ESSAY':
        answer.textAnswer = textAnswer;
        break;
    }

    dispatch(submitAnswer(answer));
    questionStartTime.current = new Date();
  };

  const handleMarkForReview = () => {
    if (currentQuestion) {
      dispatch(markForReview(currentQuestion.id));
    }
  };

  const handleNextQuestion = () => {
    handleSaveAnswer();
    dispatch(nextQuestion());
    resetAnswerState();
  };

  const handlePreviousQuestion = () => {
    handleSaveAnswer();
    dispatch(previousQuestion());
    resetAnswerState();
  };

  const handleQuestionSelect = (index: number) => {
    handleSaveAnswer();
    dispatch(goToQuestion(index));
    resetAnswerState();
  };

  const resetAnswerState = () => {
    setSelectedAnswer('');
    setTextAnswer('');
    setNumericalAnswer('');
  };

  const handleSubmitExam = () => {
    setShowSubmitDialog(true);
  };

  const confirmSubmitExam = () => {
    handleSaveAnswer();
    dispatch(submitExam());
    setShowSubmitDialog(false);
    navigate('/results');
  };

  const renderQuestion = () => {
    if (!currentQuestion) return null;

    const currentAnswer = answers[currentQuestion.id];

    return (
      <Card elevation={2}>
        <CardContent>
          <Box display="flex" justifyContent="space-between" alignItems="center" mb={2}>
            <Typography variant="h6" component="h2">
              Question {currentQuestionIndex + 1} of {questions.length}
            </Typography>
            <Box>
              <Chip 
                label={`${currentQuestion.marks} marks`} 
                color="primary" 
                size="small" 
                sx={{ mr: 1 }}
              />
              {currentQuestion.negativeMarks && (
                <Chip 
                  label={`-${currentQuestion.negativeMarks} marks`} 
                  color="error" 
                  size="small" 
                />
              )}
            </Box>
          </Box>

          <Typography variant="body1" paragraph>
            {currentQuestion.question}
          </Typography>

          {currentQuestion.type === 'SINGLE_CHOICE' && (
            <FormControl component="fieldset">
              <RadioGroup
                value={selectedAnswer}
                onChange={(e) => handleAnswerChange(e.target.value)}
              >
                {currentQuestion.options?.map((option) => (
                  <FormControlLabel
                    key={option.id}
                    value={option.id}
                    control={<Radio />}
                    label={option.text}
                  />
                ))}
              </RadioGroup>
            </FormControl>
          )}

          {currentQuestion.type === 'MULTIPLE_CHOICE' && (
            <FormGroup>
              {currentQuestion.options?.map((option) => (
                <FormControlLabel
                  key={option.id}
                  control={
                    <Checkbox
                      checked={(selectedAnswer as string[]).includes(option.id)}
                      onChange={(e) => {
                        const current = selectedAnswer as string[];
                        if (e.target.checked) {
                          handleAnswerChange([...current, option.id]);
                        } else {
                          handleAnswerChange(current.filter(id => id !== option.id));
                        }
                      }}
                    />
                  }
                  label={option.text}
                />
              ))}
            </FormGroup>
          )}

          {currentQuestion.type === 'NUMERICAL' && (
            <TextField
              type="number"
              value={numericalAnswer}
              onChange={(e) => setNumericalAnswer(Number(e.target.value) || '')}
              placeholder="Enter numerical answer"
              fullWidth
              margin="normal"
            />
          )}

          {(currentQuestion.type === 'FILL_BLANK' || currentQuestion.type === 'ESSAY') && (
            <TextField
              multiline
              rows={currentQuestion.type === 'ESSAY' ? 6 : 2}
              value={textAnswer}
              onChange={(e) => setTextAnswer(e.target.value)}
              placeholder="Enter your answer"
              fullWidth
              margin="normal"
            />
          )}

          {currentAnswer?.isMarkedForReview && (
            <Alert severity="info" sx={{ mt: 2 }}>
              This question is marked for review
            </Alert>
          )}
        </CardContent>
      </Card>
    );
  };

  if (!currentExam) {
    return (
      <Box display="flex" justifyContent="center" alignItems="center" minHeight="100vh">
        <Typography>Loading exam...</Typography>
      </Box>
    );
  }

  return (
    <Box sx={{ height: '100vh', display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
      {/* Header */}
      <Paper elevation={1} sx={{ p: 2, zIndex: 1000 }}>
        <Grid container alignItems="center" justifyContent="space-between">
          <Grid item>
            <Typography variant="h5" component="h1">
              {currentExam.title}
            </Typography>
          </Grid>
          <Grid item>
            <ExamTimer timeRemaining={timeRemaining} />
          </Grid>
        </Grid>
      </Paper>

      {/* Violations Alert */}
      {violations.length > 0 && (
        <Alert severity="warning" sx={{ m: 1 }}>
          {violations.length} violation(s) detected. Please maintain exam integrity.
        </Alert>
      )}

      <Box sx={{ display: 'flex', flex: 1, overflow: 'hidden' }}>
        {/* Question Area */}
        <Box sx={{ flex: 1, p: 2, overflow: 'auto' }}>
          {renderQuestion()}
        </Box>

        {/* Sidebar */}
        <Box sx={{ width: 300, borderLeft: 1, borderColor: 'divider' }}>
          {/* Question Palette */}
          <QuestionPalette
            questions={questions}
            answers={answers}
            currentQuestionIndex={currentQuestionIndex}
            onQuestionSelect={handleQuestionSelect}
          />

          {/* Proctoring Monitor */}
          {proctoringEnabled && currentExam.settings.proctoring.enabled && (
            <ProctoringMonitor
              webcamRef={webcamRef}
              examId={examId!}
              settings={currentExam.settings.proctoring}
            />
          )}
        </Box>
      </Box>

      {/* Navigation Footer */}
      <Paper elevation={2} sx={{ p: 2, mt: 'auto' }}>
        <Grid container justifyContent="space-between" alignItems="center">
          <Grid item>
            <Button
              startIcon={<NavigateBefore />}
              onClick={handlePreviousQuestion}
              disabled={currentQuestionIndex === 0}
            >
              Previous
            </Button>
          </Grid>
          
          <Grid item>
            <Box display="flex" gap={1}>
              <Button
                variant="outlined"
                onClick={handleSaveAnswer}
                startIcon={<Visibility />}
              >
                Save
              </Button>
              <Button
                variant="outlined"
                color="warning"
                onClick={handleMarkForReview}
                startIcon={<Flag />}
              >
                Mark for Review
              </Button>
            </Box>
          </Grid>

          <Grid item>
            {currentQuestionIndex === questions.length - 1 ? (
              <Button
                variant="contained"
                color="success"
                onClick={handleSubmitExam}
                startIcon={<Send />}
                disabled={isSubmitting}
              >
                Submit Exam
              </Button>
            ) : (
              <Button
                endIcon={<NavigateNext />}
                onClick={handleNextQuestion}
              >
                Next
              </Button>
            )}
          </Grid>
        </Grid>
      </Paper>

      {/* Submit Confirmation Dialog */}
      <Dialog open={showSubmitDialog} onClose={() => setShowSubmitDialog(false)}>
        <DialogTitle>Submit Exam</DialogTitle>
        <DialogContent>
          <Typography>
            Are you sure you want to submit the exam? This action cannot be undone.
          </Typography>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setShowSubmitDialog(false)}>Cancel</Button>
          <Button onClick={confirmSubmitExam} variant="contained" color="primary">
            Submit
          </Button>
        </DialogActions>
      </Dialog>

      {/* Hidden webcam for proctoring */}
      {proctoringEnabled && currentExam.settings.proctoring.enabled && (
        <Box sx={{ position: 'fixed', bottom: 10, right: 10, width: 160, height: 120 }}>
          <Webcam
            ref={webcamRef}
            audio={currentExam.settings.proctoring.audioRecording}
            width={160}
            height={120}
            screenshotFormat="image/jpeg"
            videoConstraints={{
              width: 640,
              height: 480,
              facingMode: 'user',
            }}
          />
        </Box>
      )}
    </Box>
  );
};

export default ExamInterface;