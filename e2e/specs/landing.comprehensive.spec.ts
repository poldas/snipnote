import { test, expect } from '@playwright/test';
import { LandingPage } from '../page-objects/LandingPage';

test.describe('Landing Page - Comprehensive Suite', () => {
    let landingPage: LandingPage;

    test.beforeEach(async ({ page }) => {
        landingPage = new LandingPage(page);
        await landingPage.goto();
    });

    test('should have correct structure and essential copy', async () => {
        // Assert Header
        await expect(landingPage.page.getByTestId('nav-login-link')).toHaveText('Zaloguj się');
        await expect(landingPage.page.getByTestId('nav-register-link')).toHaveText('Załóż konto');

        // Assert Hero
        await landingPage.expectHeroVisible();
        await landingPage.expectHeroSubtitleVisible();
        await expect(landingPage.page.getByText('Szybkie, bezpieczne i przejrzyste notatki, które łatwo udostępnisz innym.')).toBeVisible();
        await landingPage.expectBenefitTagsVisible();
        
        // Verify Login Form Headers
        await landingPage.expectLoginFormHeadersVisible();

        // Verify All Sections are present
        await landingPage.expectFeaturesSectionVisible();
        await landingPage.expectHowItWorksSectionVisible();

        // Detailed Steps Verification
        await landingPage.expectStepDetails(1, 'Twórz notatki', 'Zapisuj pomysły w przejrzystym edytorze z Markdown');
        await landingPage.expectStepDetails(2, 'Organizuj', 'Dodawaj tagi, edytuj i współdziel notatki');
        await landingPage.expectStepDetails(3, 'Udostępniaj', 'Współpracuj z zespołem lub dziel się publicznie.');

        await landingPage.expectCTASectionVisible();
        
        // Footer Verification
        await landingPage.expectFooterVisible();
        await expect(landingPage.page.locator('footer p').first()).toHaveText(/Najlepsze narzędzie do tworzenia, organizowania i udostępniania notatek/);
    });

    test('should display exactly 6 feature cards with correct content', async () => {
        // Assert count
        await landingPage.expectFeatureCardsCount(6);

        // Assert specific content for each card (matching the latest implementation)
        const features = [
            { title: 'Błyskawicznie szybkie', desc: 'Twórz notatki w mgnieniu oka. Żadnych opóźnień.' },
            { title: 'Bezpieczne i prywatne', desc: 'Kontroluj dostęp do swoich notatek. Zmień widoczność w dowolnym momencie.' },
            { title: 'Udostępnianie', desc: 'Udostępnij anonimowy link notatki. Tylko osoby z linkiem widzą notatkę.' },
            { title: 'Szybkie szukanie', desc: 'Błyskawicznie znajdź to, czego potrzebujesz dzięki filtrom.' },
            { title: 'Wspólne notatki', desc: 'Edytuj notatki z innymi. Dodaj osobę i udostępnij link.' },
            { title: 'Piękny Markdown', desc: 'Markdown, składnia kodu, listy kontrolne. Wszystko czego potrzebujesz.' }
        ];

        for (let i = 0; i < features.length; i++) {
            await landingPage.expectFeatureCardDetails(i + 1, features[i].title, features[i].desc);
        }
    });

    test('should have correct visual identity (colors and gradients)', async () => {
        // Verify main text color (indigo-950)
        const headline = landingPage.page.locator('h1').first();
        await expect(headline).toHaveCSS('color', 'rgb(30, 27, 75)'); // text-indigo-950

        // Visual snapshot for regression with threshold for anti-aliasing
        await expect(landingPage.page).toHaveScreenshot('landing-full-page.png', { 
            threshold: 0.2,
            maxDiffPixelRatio: 0.05 
        });
    });

    test('should demonstrate hover and focus animations', async () => {
        // Hover on Logo
        await landingPage.expectLogoHoverEffect();

        // Focus on Inputs (should show shadow-xl / transition)
        await landingPage.expectInputFocusEffect('email');
        await landingPage.expectInputFocusEffect('password');

        // Hover on Feature Card
        await landingPage.expectCardHoverEffect(1);
    });

    test('should be responsive (mobile viewport layout)', async () => {
        // Set viewport to mobile
        await landingPage.page.setViewportSize({ width: 375, height: 667 });

        // Verify icons and titles are side-by-side (flex row) on mobile
        // We look for the specific container that has flex row on mobile but not on desktop (sm:block)
        const firstCardHeader = landingPage.page.getByTestId('feature-card-1').locator('> div').first();
        await expect(firstCardHeader).toHaveClass(/flex items-center/);
        
        // Take mobile screenshot with threshold for anti-aliasing
        await expect(landingPage.page).toHaveScreenshot('landing-mobile.png', {
            threshold: 0.2,
            maxDiffPixelRatio: 0.05
        });
    });
});
