import { Controller, Get } from '@nestjs/common';
import { HealthService } from './health.service';

@Controller('health')
export class HealthController {
  constructor(private readonly healthService: HealthService) {}

  @Get()
  async checkHealth() {
    return this.healthService.getHealth();
  }

  @Get('ready')
  async checkReadiness() {
    return this.healthService.getReadiness();
  }

  @Get('live')
  async checkLiveness() {
    return this.healthService.getLiveness();
  }
}