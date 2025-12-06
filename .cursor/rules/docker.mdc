---
description: 
globs: 
alwaysApply: false
---
# Docker Best Practices for Modern Web Applications

## Dockerfile Structure

### Naming and Conventions
- Always use UPPERCASED keywords

### Base Image Selection
- Always use specific version tags matching tech stack (e.g., `node:22-alpine` not `node:latest`)
- Use images compatible with CI/CD & Github Actions runner - `ubuntu-latest`
- Choose slim/alpine variants for production images
- Use two-stage builds to separate build and runtime environments
- Analyze deps and scripts that may cause issues when switching between build and runtime step (i.e. devDependency required in "prepare", but not installed)

### Instruction Order
1. FROM - Base image definition
2. ARG - Build-time variables (can also appear before FROM)
3. LABEL - Image metadata
4. ENV - Environment variables
5. WORKDIR - Set working directory
6. RUN - Execute commands
7. COPY - Add files from build context
8. USER - Switch to non-root user
9. EXPOSE - Document ports
10. HEALTHCHECK - Container health monitoring
11. CMD - Default command

### Essential Security Practices
- Run containers as non-root users
- Never store secrets or credentials in images
- Set appropriate file permissions
- Use COPY instead of ADD for predictable behavior
- Scan images for vulnerabilities before deployment

### Optimization Essentials
- Use .dockerignore to exclude unnecessary files
- Order instructions by change frequency (least frequent first)
- Cache dependency installation separately from application code
- Copy only what's needed in multi-stage builds using `--from`
- Clean up package manager caches in the same layer they're created

### Health & Monitoring
- Always implement HEALTHCHECK instructions
- Configure logging to stdout/stderr
- Document exposed ports with EXPOSE instruction

## Docker Commands

### Building
- Use versioning based on commit SHA for image tags
- Add metadata with labels
- Enable BuildKit for better performance
- Always build app in production mode based on provided tech stack
- Ask if `node_modules` directory is required in runtime

### Running
- Set appropriate resource limits
- Use read-only filesystem when possible
- Name containers for easier management

### Docker Compose
- Use version '3' or higher syntax
- Define networks explicitly
- Set appropriate restart policies

# Application Configuration

## Networking

- Adjust app to use HOST 0.0.0.0 and PORT 8080 (for Docker Build / Run) - default to currently used value
