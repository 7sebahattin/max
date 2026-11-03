# MAX Branding and Legal Identity

## Design goal
MAX is a product, not a company-specific system. One centralized configuration change should update the visible application identity throughout custom pages. Every customer installation may use a different visual brand and independently configured legal company identity.

### Product appearance, per installation
- Product/app display name (default: MAX).
- Logo, favicon, login-screen title, browser title, app icon and menu brand.
- Primary theme/accent, UI language, optional footer and generic support contact.
- Email sender *display* name and signature template where permitted.

### Legal entity, per installation
- Official company name, registration/tax IDs, address, contacts.
- Bank accounts and payment details; invoice issuer.
- Official document footer, legal statements and approved template versions.
- Document serials and regulatory identity governed by the ERP's own configuration and legal requirements.

### Non-negotiable rules
- **Do not concatenate** customer display branding with legal issuer text: they represent different facts.
- **Never hardcode** a customer name, tax number, account number, brand, destination, transport term or logo path in a MAX source file.
- **Do not rename** PHP class names, database tables, routes or the GitHub repository when changing the brand.
- For standard Dolibarr screens, use supported configuration, hooks or replaceable assets where possible. Any upstream limitations must be explicitly documented; do not promise a complete white-label removal of upstream legal notices.
- Always retain legally required Dolibarr copyright, GPL and trademark attribution.
- Use neutral names in examples and synthetic test fixtures.

## Acceptance criteria
1. Two clean installations can show different brand labels and logos without code modifications.
2. Altering display brand must not alter invoice legal entity or tax data.
3. Changing a legal entity must not silently change the public application brand.
4. Mobile and desktop navigation, PDF templates, generated emails and app metadata are checked for old/customer-specific names.
5. Brand settings survive a supported upstream Dolibarr update.
