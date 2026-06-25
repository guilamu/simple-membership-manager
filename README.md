# Simple Membership Manager

[![Latest Release](https://img.shields.io/github/v/release/guilamu/simple-membership-manager?color=blue)](https://github.com/guilamu/simple-membership-manager/releases) [![License: GPL-3.0-or-later](https://img.shields.io/badge/license-GPL--3.0--or--later-green.svg)](https://github.com/guilamu/simple-membership-manager) [![WordPress: 5.0+](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org) [![PHP: 7.0+](https://img.shields.io/badge/PHP-7.0%2B-purple.svg)](https://php.net)

Lightweight, zero-dependency drop-in replacement for Restrict Content Pro, compatible with the same database tables and menu visibility hooks.

---

## Access Control & Content Restriction
- Choose from multiple restriction rules: by subscription level, access level (0-10), registered users, or specific user roles.
- Show custom teaser message with a dynamic post excerpt to encourage registration.
- Restrict content easily in the post editor screen using the dedicated metabox, or via shortcodes inside post content.

## Membership & Customer Administration
- View and manage customers and their active, expired, or cancelled memberships inside the dedicated admin screens.
- Set specific expiration dates, date created, auto-renewals, and custom administrative notes for each membership.
- Manage levels with custom durations (days/weeks/months/years), price, signup fees, trial periods, and roles.

## Key Features
- **Drop-in Compatibility:** Reuse the exact database tables of Restrict Content Pro without modifying schema.
- **Multilingual:** Works with content in any language.
- **Translation-Ready:** All strings are internationalized.
- **Secure:** Enforces native WordPress nonce verification, capabilities, and sanitization on all processes.
- **GitHub Updates:** Automatic updates from GitHub releases.

## Requirements
- WordPress 5.0 or higher
- PHP 7.0 or higher

## Installation

### Replacing Restrict Content Pro (RCP) with SMM:
1. **Deactivate Restrict Content Pro (RCP):** Go to the **Plugins** page in WordPress and deactivate RCP. 
   > [!WARNING]
   > Do **not** keep both RCP and SMM active at the same time to avoid PHP class redeclaration conflicts.
2. **Upload SMM:** Upload the `simple-membership-manager` folder to the `/wp-content/plugins/` directory.
3. **Activate SMM:** Activate **Simple Membership Manager** through the **Plugins** menu.
4. **Data Verification:** Go to **Restrict → Memberships**. SMM will automatically detect and reuse your existing RCP database tables, seamlessly picking up all your customers, subscription levels, active/expired/cancelled memberships, and notes without any data loss.

## FAQ

### Does SMM support payment processing?
No, Simple Membership Manager is a lightweight drop-in replacement focused on membership management, content restriction, and user role synchronization. It does not contain any payment gateways or payment processing logic.

### Can I customize the restricted content message?
Yes, you can use the `rcp_restricted_message_filter` filter to customize the message shown to non-members:
```php
add_filter( 'rcp_restricted_message_filter', function( $message ) {
    return '<p>Custom warning message here.</p>';
} );
```

### Is SMM compatible with menu visibility controls?
Yes, SMM implements the exact function compatibility stubs used by the `advanced-menu-items-visibility-control` plugin to hide or show menu items based on membership levels.

## Project Structure
```
.
├── simple-membership-manager.php      # Main plugin file & bootstrap
├── uninstall.php                      # Cleanup on uninstall
├── README.md                          # Documentation and help text
├── includes
│   ├── class-github-updater.php       # GitHub auto-updates handler
│   ├── Parsedown.php                  # Markdown parser for updater popup
│   ├── db.php                         # Direct database query layers
│   ├── class-smm-membership-level.php # Membership level compatibility class
│   ├── class-smm-customer.php         # Customer compatibility class
│   ├── class-smm-membership.php       # Membership compatibility class
│   ├── functions-compat.php           # RCP function stubs layer
│   ├── content-restriction.php        # Content filters, shortcodes, metaboxes
│   └── emails.php                     # Emails template sender class
├── admin
│   ├── admin-pages.php                # Menu page registration
│   ├── members-page.php               # Memberships CRUD interface
│   ├── customers-page.php             # Customers listings
│   ├── levels-page.php                # Membership levels CRUD
│   ├── metabox-view.php               # Restriction metabox HTML layout
│   └── admin-styles.css               # Admin stylesheet overrides
└── languages
    └── rcp.pot                        # Translation template file
```

## Changelog

### 1.0.0 - 2026-06-25
- Initial release
- Drop-in compatibility for Restrict Content Pro database tables
- Standard user management, level CRUD, and customer overview pages
- Integration of GitHub auto-updates and bug reporting utility

## Security

If you discover a security vulnerability in this plugin, please report it responsibly through [GitHub Security Advisories](https://github.com/guilamu/simple-membership-manager/security/advisories/new). Do not open a public issue for security reports.

## Contributing

Contributions are welcome! Please open an issue or submit a pull request on [GitHub](https://github.com/guilamu/simple-membership-manager).

For translations, the plugin uses WordPress i18n. You can contribute translations by editing the `.po` files in the `languages/` directory and generating the corresponding `.mo` files with the `wp i18n` CLI commands.

## License
This project is licensed under the GNU General Public License v3.0 or later (GPL-3.0-or-later).

---

Made with love for the WordPress community
