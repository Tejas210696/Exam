# Contributing to JEE Online Examination Portal

Thank you for your interest in contributing to the JEE Online Examination Portal! This document provides guidelines and information for contributors.

## 🤝 Code of Conduct

By participating in this project, you agree to abide by our Code of Conduct:

- Be respectful and inclusive
- Focus on constructive feedback
- Help create a welcoming environment for all contributors
- Report any unacceptable behavior to the maintainers

## 🚀 Getting Started

### Prerequisites

- Node.js 18+ and npm
- Docker and Docker Compose
- Git
- Basic knowledge of TypeScript, React, and NestJS

### Development Setup

1. Fork and clone the repository
```bash
git clone https://github.com/your-username/jee-examination-portal.git
cd jee-examination-portal
```

2. Run the setup script
```bash
chmod +x scripts/setup-dev.sh
./scripts/setup-dev.sh
```

3. Start the development environment
```bash
npm run dev
```

## 📋 How to Contribute

### Reporting Issues

Before creating an issue, please:

1. Search existing issues to avoid duplicates
2. Use the appropriate issue template
3. Provide clear reproduction steps
4. Include environment details

### Submitting Pull Requests

1. **Create a feature branch**
```bash
git checkout -b feature/your-feature-name
```

2. **Make your changes**
   - Follow the coding standards
   - Add tests for new functionality
   - Update documentation as needed

3. **Test your changes**
```bash
npm test
npm run test:e2e
npm run lint
```

4. **Commit your changes**
```bash
git add .
git commit -m "feat: add new feature description"
```

5. **Push and create PR**
```bash
git push origin feature/your-feature-name
```

## 📝 Coding Standards

### TypeScript/JavaScript

- Use TypeScript for all new code
- Follow ESLint and Prettier configurations
- Use meaningful variable and function names
- Add JSDoc comments for public APIs
- Prefer const over let, avoid var

### React Components

- Use functional components with hooks
- Follow the component structure:
  ```tsx
  import React from 'react';
  import { ComponentProps } from './types';
  
  interface Props extends ComponentProps {
    // component-specific props
  }
  
  const ComponentName: React.FC<Props> = ({ prop1, prop2 }) => {
    // hooks
    // handlers
    // render
  };
  
  export default ComponentName;
  ```

### NestJS Services

- Use dependency injection
- Follow SOLID principles
- Add proper error handling
- Use DTOs for validation
- Add comprehensive tests

### Database

- Use migrations for schema changes
- Add proper indexes
- Follow naming conventions
- Add data validation

## 🧪 Testing Guidelines

### Unit Tests

- Write tests for all business logic
- Use Jest for testing framework
- Aim for >90% code coverage
- Mock external dependencies

### Integration Tests

- Test API endpoints
- Test database interactions
- Use test databases
- Clean up after tests

### E2E Tests

- Test critical user journeys
- Use Playwright for browser testing
- Test on multiple browsers
- Include accessibility testing

## 📚 Documentation

- Update README.md for significant changes
- Add JSDoc comments for public APIs
- Update API documentation
- Include examples in documentation

## 🏗️ Architecture Guidelines

### Microservices

- Keep services focused and cohesive
- Use proper service boundaries
- Implement proper error handling
- Add health checks

### Database Design

- Use appropriate database for data type
- Implement proper relationships
- Add proper constraints
- Consider performance implications

### Security

- Never commit secrets
- Use environment variables
- Implement proper authentication
- Validate all inputs
- Follow OWASP guidelines

## 🔄 Git Workflow

### Commit Messages

Follow the Conventional Commits specification:

- `feat:` new features
- `fix:` bug fixes
- `docs:` documentation changes
- `style:` formatting changes
- `refactor:` code refactoring
- `test:` adding tests
- `chore:` maintenance tasks

### Branch Naming

- `feature/description` - new features
- `fix/description` - bug fixes
- `docs/description` - documentation
- `refactor/description` - refactoring

## 🚦 CI/CD Pipeline

All PRs must pass:

- Linting checks
- Unit tests
- Integration tests
- Security scans
- Build verification

## 📋 Review Process

### Code Review Checklist

- [ ] Code follows style guidelines
- [ ] Tests are included and passing
- [ ] Documentation is updated
- [ ] No security vulnerabilities
- [ ] Performance considerations addressed
- [ ] Backward compatibility maintained

### Review Guidelines

- Be constructive and respectful
- Focus on the code, not the person
- Explain the reasoning behind suggestions
- Approve when ready, request changes when needed

## 🏷️ Release Process

1. Update version numbers
2. Update CHANGELOG.md
3. Create release PR
4. Tag release after merge
5. Deploy to staging
6. Deploy to production

## 🆘 Getting Help

- Check existing documentation
- Search closed issues
- Ask in discussions
- Contact maintainers

## 📄 License

By contributing, you agree that your contributions will be licensed under the MIT License.

## 🙏 Recognition

Contributors will be recognized in:

- README.md contributors section
- Release notes
- Annual contributor report

Thank you for contributing to the JEE Online Examination Portal! 🎉