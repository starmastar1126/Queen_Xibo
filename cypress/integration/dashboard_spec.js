describe('Dashboard', function() {

    beforeEach(function() {
        cy.login();
    });

    it('should be at the dashboard page', function() {

        cy.visit('/statusdashboard');


        cy.url().should('include', 'dashboard');

        // Check for the dashboard elements
        cy.contains('Bandwidth Usage');
        cy.contains('Library Usage');
        cy.contains('Display Activity');
        cy.contains('Latest News');
    });

    it('should go to the welcome page, show a tutorial, and then disable it', function() {
        cy.server();

        cy.visit('/statusdashboard');

        // Open user dropdown menu
        cy.get('#navbarUserMenu img.nav-avatar').click();

        // Click Reshow welcome
        cy.get('#reshowWelcomeMenuItem').click();

        cy.url().should('include', 'welcome');

        cy.get('div[data-tour-name="mainTour').click();

        cy.get('.popover.tour').should('to.be.visible');

        // Click to disable welcome tour
        cy.get('button[data-role="end"]').click();

        cy.wait(500);
        cy.get('.popover.tour').should('not.exist');
    });
});