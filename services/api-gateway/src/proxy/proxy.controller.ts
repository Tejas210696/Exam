import { Controller, All, Req, Res } from '@nestjs/common';
import { Request, Response } from 'express';
import { ProxyService } from './proxy.service';

@Controller()
export class ProxyController {
  constructor(private readonly proxyService: ProxyService) {}

  @All('auth/*')
  async proxyAuth(@Req() req: Request, @Res() res: Response) {
    return this.proxyService.proxyRequest(req, res, 'auth-service', 8001);
  }

  @All('users/*')
  async proxyUsers(@Req() req: Request, @Res() res: Response) {
    return this.proxyService.proxyRequest(req, res, 'user-service', 8002);
  }

  @All('exams/*')
  async proxyExams(@Req() req: Request, @Res() res: Response) {
    return this.proxyService.proxyRequest(req, res, 'exam-service', 8003);
  }

  @All('questions/*')
  async proxyQuestions(@Req() req: Request, @Res() res: Response) {
    return this.proxyService.proxyRequest(req, res, 'question-service', 8004);
  }

  @All('submissions/*')
  async proxySubmissions(@Req() req: Request, @Res() res: Response) {
    return this.proxyService.proxyRequest(req, res, 'submission-service', 8005);
  }

  @All('evaluations/*')
  async proxyEvaluations(@Req() req: Request, @Res() res: Response) {
    return this.proxyService.proxyRequest(req, res, 'evaluation-service', 8006);
  }

  @All('proctor/*')
  async proxyProctor(@Req() req: Request, @Res() res: Response) {
    return this.proxyService.proxyRequest(req, res, 'proctor-service', 8007);
  }
}