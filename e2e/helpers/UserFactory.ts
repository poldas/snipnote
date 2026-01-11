import { execSync } from 'child_process';

/**
 * UserFactory provides a way to create unique users for each test spec.
 * This ensures total isolation and allows maximum parallelism.
 */
export class UserFactory {
    static generateEmail(prefix: string = 'test'): string {
        return `${prefix}_${Date.now()}_${Math.floor(Math.random() * 1000)}@test.test`;
    }

    static async create(email: string, pass: string = 'K7pL9mW3xR8vT2q'): Promise<void> {
        let lastError;
        // In CI (GitHub Actions) we now run Docker Compose, so we generally want to use it.
        // Only disable if explicitly requested (e.g. strict local dev without docker).
        const useDocker = process.env.E2E_DOCKER_DISABLED !== '1';
        const commandPrefix = useDocker ? 'docker compose exec -T app ' : '';
        // Use test environment in CI, dev environment locally (since web app runs in dev)
        // If running inside docker container via exec, strict environment might be inherited, 
        // but passing flag is safer.
        const envFlag = process.env.CI ? '--env=test' : '--env=dev';

        for (let i = 0; i < 3; i++) {
            try {
                const command = `${commandPrefix}php bin/console app:test-user-manage create ${email} ${pass} ${envFlag}`.trim();
                execSync(command, { stdio: 'pipe' }); // Pipe stdio to avoid cluttering logs unless error
                return; // Success
            } catch (error) {
                lastError = error;
                await new Promise(resolve => setTimeout(resolve, 2000)); // Wait before retry
            }
        }
        throw new Error(`Failed to create test user ${email} after retries. Last error: ${lastError}`);
    }

    static async delete(email: string): Promise<void> {
        try {
            const useDocker = process.env.E2E_DOCKER_DISABLED !== '1';
            const commandPrefix = useDocker ? 'docker compose exec -T app ' : '';
            const envFlag = process.env.CI ? '--env=test' : '--env=dev';
            const command = `${commandPrefix}php bin/console app:test-user-manage delete ${email} --no-interaction ${envFlag}`;
            execSync(command, { stdio: 'ignore' });
        } catch (error) {
            // Silently ignore deletion errors
        }
    }
}
