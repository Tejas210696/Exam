import { Injectable, Logger } from '@nestjs/common';
import { Request, Response } from 'express';
import { createProxyMiddleware } from 'http-proxy-middleware';

@Injectable()
export class ProxyService {
  private readonly logger = new Logger(ProxyService.name);
  private readonly serviceInstances = new Map<string, string[]>();

  constructor() {
    // Initialize service instances for load balancing
    this.initializeServiceInstances();
  }

  private initializeServiceInstances() {
    const isProduction = process.env.NODE_ENV === 'production';
    
    if (isProduction) {
      // In production, use Kubernetes service discovery
      this.serviceInstances.set('auth-service', ['http://auth-service:8001']);
      this.serviceInstances.set('user-service', ['http://user-service:8002']);
      this.serviceInstances.set('exam-service', ['http://exam-service:8003']);
      this.serviceInstances.set('question-service', ['http://question-service:8004']);
      this.serviceInstances.set('submission-service', ['http://submission-service:8005']);
      this.serviceInstances.set('evaluation-service', ['http://evaluation-service:8006']);
      this.serviceInstances.set('proctor-service', ['http://proctor-service:8007']);
    } else {
      // Development environment
      this.serviceInstances.set('auth-service', ['http://localhost:8001']);
      this.serviceInstances.set('user-service', ['http://localhost:8002']);
      this.serviceInstances.set('exam-service', ['http://localhost:8003']);
      this.serviceInstances.set('question-service', ['http://localhost:8004']);
      this.serviceInstances.set('submission-service', ['http://localhost:8005']);
      this.serviceInstances.set('evaluation-service', ['http://localhost:8006']);
      this.serviceInstances.set('proctor-service', ['http://localhost:8007']);
    }
  }

  async proxyRequest(req: Request, res: Response, serviceName: string, defaultPort: number) {
    const target = this.getServiceTarget(serviceName, defaultPort);
    
    const proxy = createProxyMiddleware({
      target,
      changeOrigin: true,
      pathRewrite: {
        [`^/api/v1/${serviceName.split('-')[0]}`]: '',
      },
      onError: (err, req, res) => {
        this.logger.error(`Proxy error for ${serviceName}:`, err.message);
        res.status(503).json({
          success: false,
          message: 'Service temporarily unavailable',
          error: 'PROXY_ERROR',
        });
      },
      onProxyReq: (proxyReq, req, res) => {
        this.logger.log(`Proxying ${req.method} ${req.url} to ${target}`);
      },
      timeout: 30000,
      proxyTimeout: 30000,
    });

    return proxy(req, res, (err) => {
      if (err) {
        this.logger.error(`Proxy middleware error:`, err);
        res.status(503).json({
          success: false,
          message: 'Service temporarily unavailable',
          error: 'PROXY_MIDDLEWARE_ERROR',
        });
      }
    });
  }

  private getServiceTarget(serviceName: string, defaultPort: number): string {
    const instances = this.serviceInstances.get(serviceName);
    
    if (!instances || instances.length === 0) {
      // Fallback to default
      const isProduction = process.env.NODE_ENV === 'production';
      return isProduction ? `http://${serviceName}:${defaultPort}` : `http://localhost:${defaultPort}`;
    }

    // Simple round-robin load balancing
    const randomIndex = Math.floor(Math.random() * instances.length);
    return instances[randomIndex];
  }
}