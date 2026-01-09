import { test, expect } from '@playwright/test';
import { LoginPage } from '../page-objects/LoginPage';
import { DashboardPage } from '../page-objects/DashboardPage';
import { NoteEditorPage } from '../page-objects/NoteEditorPage';
import { PublicNotePage } from '../page-objects/PublicNotePage';
import { UserFactory } from '../helpers/UserFactory';

test.describe('Note Themes - Visual differences', () => {
    let userEmail: string;
    const userPass = 'K7pL9mW3xR8vT2q';

    test.beforeAll(async () => {
        userEmail = UserFactory.generateEmail('themes');
        await UserFactory.create(userEmail, userPass);
    });

    test.afterAll(async () => {
        await UserFactory.delete(userEmail);
    });

    test.beforeEach(async ({ page }) => {
        const loginPage = new LoginPage(page);
        await loginPage.goto();
        await loginPage.login(userEmail, userPass);
    });

    test('should display TODO theme correctly', async ({ page, context }) => {
        const dashboardPage = new DashboardPage(page);
        const editorPage = new NoteEditorPage(page);
        const todoTitle = `Todo List ${Date.now()}`;

        await dashboardPage.clickAddNote();
        await editorPage.fillTitle(todoTitle);
        await editorPage.fillDescription(`- [ ] Task 1`);
        await editorPage.addLabel('todo');
        await editorPage.setVisibility('public');
        await editorPage.save();

        const [todoPage] = await Promise.all([
            context.waitForEvent('page'),
            dashboardPage.clickOpenNote(todoTitle)
        ]);
        const publicTodo = new PublicNotePage(todoPage);
        await publicTodo.expectTitle(todoTitle);
        await publicTodo.expectTodoTheme();
        await todoPage.close();
    });

    test('should display RECIPE theme correctly', async ({ page, context }) => {
        const dashboardPage = new DashboardPage(page);
        const editorPage = new NoteEditorPage(page);
        const recipeTitle = `Recipe ${Date.now()}`;

        await dashboardPage.clickAddNote();
        await editorPage.fillTitle(recipeTitle);
        await editorPage.fillDescription(`# Ingredients`);
        await editorPage.addLabel('recipe');
        await editorPage.setVisibility('public');
        await editorPage.save();

        const [recipePage] = await Promise.all([
            context.waitForEvent('page'),
            dashboardPage.clickOpenNote(recipeTitle)
        ]);
        const publicRecipe = new PublicNotePage(recipePage);
        await publicRecipe.expectTitle(recipeTitle);
        await publicRecipe.expectRecipeTheme();
        await recipePage.close();
    });
});