import { Injectable } from '@nestjs/common';

@Injectable()
export class HealthService {
  async getHealth() {
    const services = await this.checkServices();
    const isHealthy = services.every(service => service.status === 'healthy');

    return {
      status: isHealthy ? 'healthy' : 'unhealthy',
      timestamp: new Date().toISOString(),
      uptime: process.uptime(),
      services,
    };
  }

  async getReadiness() {
    const services = await this.checkServices();
    const isReady = services.every(service => service.status === 'healthy');

    return {
      status: isReady ? 'ready' : 'not ready',
      timestamp: new Date().toISOString(),
      services,
    };
  }

  async getLiveness() {
    return {
      status: 'alive',
      timestamp: new Date().toISOString(),
      uptime: process.uptime(),
      memory: process.memoryUsage(),
    };
  }

  private async checkServices() {
    const services = [
      { name: 'auth-service', url: 'http://localhost:8001/health' },
      { name: 'user-service', url: 'http://localhost:8002/health' },
      { name: 'exam-service', url: 'http://localhost:8003/health' },
      { name: 'question-service', url: 'http://localhost:8004/health' },
      { name: 'submission-service', url: 'http://localhost:8005/health' },
      { name: 'evaluation-service', url: 'http://localhost:8006/health' },
      { name: 'proctor-service', url: 'http://localhost:8007/health' },
    ];

    const results = await Promise.allSettled(
      services.map(async (service) => {
        try {
          const response = await fetch(service.url, { 
            method: 'GET',
            timeout: 5000 
          });
          return {
            name: service.name,
            status: response.ok ? 'healthy' : 'unhealthy',
            responseTime: Date.now(),
          };
        } catch (error) {
          return {
            name: service.name,
            status: 'unhealthy',
            error: error.message,
          };
        }
      }),
    );

    return results.map((result, index) => {
      if (result.status === 'fulfilled') {
        return result.value;
      } else {
        return {
          name: services[index].name,
          status: 'unhealthy',
          error: result.reason.message,
        };
      }
    });
  }
}