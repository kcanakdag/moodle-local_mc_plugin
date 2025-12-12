# MoodleConnect Plugin

[![Moodle Plugin](https://img.shields.io/badge/Moodle-4.0%2B-orange.svg)](https://moodle.org)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-GPL%20v3-blue.svg)](LICENSE)
[![Coding Standards](https://img.shields.io/badge/Coding%20Standards-Compliant-brightgreen.svg)](https://moodledev.io/general/development/policies/codingstyle)

The official Moodle plugin for [MoodleConnect](https://moodleconnect.com) - a micro-SaaS platform that bridges Moodle LMS events to external tools like Airtable, webhooks, and more.

## Overview

MoodleConnect enables Moodle administrators to automatically sync events (user creation, course enrollment, assignment submissions, etc.) to external services for reporting, automation, and data management. This plugin captures Moodle events and transmits them to the MoodleConnect service, where you can configure triggers and field mappings through an intuitive dashboard.

## Features

- **Event Monitoring**: Capture any Moodle event (user enrollments, course completions, grade changes, etc.)
- **Selective Event Tracking**: Choose exactly which events to monitor via an intuitive admin interface
- **Automatic Schema Sync**: Automatically discover and sync event field structures to MoodleConnect
- **Secure Communication**: HMAC-based authentication ensures secure data transmission
- **Debug Mode**: Built-in logging for troubleshooting connection and event issues
- **OAuth-style Connection Flow**: Simple, secure linking process between Moodle and MoodleConnect
- **Zero Performance Impact**: Asynchronous event handling doesn't slow down your Moodle site

## Requirements

- **Moodle**: 4.0 or higher
- **PHP**: 8.1 or higher
- **MoodleConnect Account**: Free account at [moodleconnect.com](https://moodleconnect.com)

## Installation

### Method 1: Via Moodle Plugin Installer (Recommended)

1. Download the latest release ZIP from the [Moodle Plugin Directory](https://moodle.org/plugins/local_mc_plugin) or [GitHub Releases](https://github.com/kcanakdag/moodle-local_mc_plugin/releases)
2. Log in to your Moodle site as an administrator
3. Navigate to **Site administration → Plugins → Install plugins**
4. Upload the ZIP file
5. Click **Install plugin from the ZIP file**
6. Follow the on-screen instructions to complete the installation

### Method 2: Manual Installation

1. Download the latest release ZIP
2. Extract the contents
3. Upload the `mc_plugin` folder to your Moodle installation's `local/` directory
4. Rename the folder to `mc_plugin` if necessary (final path should be `moodle/local/mc_plugin`)
5. Log in to your Moodle site as an administrator
6. Navigate to **Site administration → Notifications**
7. Follow the prompts to complete the installation

### Method 3: Via Git (For Developers)

```bash
cd /path/to/moodle/local
git clone https://github.com/kcanakdag/moodle-local_mc_plugin.git mc_plugin
```

Then visit **Site administration → Notifications** to complete the installation.

## Configuration

### Step 1: Create a MoodleConnect Account

1. Visit [moodleconnect.com](https://moodleconnect.com)
2. Sign up for a free account
3. Create a new site in the dashboard
4. Copy your **Site Key** and **MoodleConnect URL**

### Step 2: Connect Your Moodle Site

1. In Moodle, navigate to **Site administration → Plugins → Local plugins → MoodleConnect**
2. You'll see the connection interface with a **Connect to MoodleConnect** button
3. Click the button to initiate the OAuth-style connection flow
4. You'll be redirected to MoodleConnect to authorize the connection
5. After authorization, you'll be redirected back to Moodle
6. Your site is now connected! You'll see a green "Connected" status

### Step 3: Select Events to Monitor

1. In the plugin settings, scroll to the **Event Selection** section
2. Browse or search for events you want to monitor
3. Check the boxes next to events you want to track (e.g., `\core\event\user_created`, `\core\event\course_completed`)
4. Click **Save changes**

**Popular events to monitor:**
- `\core\event\user_created` - New user registrations
- `\core\event\user_enrolment_created` - Course enrollments
- `\core\event\course_completed` - Course completions
- `\mod_assign\event\assessable_submitted` - Assignment submissions
- `\core\event\user_graded` - Grade changes

### Step 4: Sync Event Schemas

1. Navigate to **Site administration → Plugins → Local plugins → MoodleConnect → Sync Schemas**
2. Click **Sync All Schemas** to send event field structures to MoodleConnect
3. Wait for the sync to complete (you'll see a success message)

This step tells MoodleConnect what data fields are available in each event, enabling smart field mapping in the dashboard.

### Step 5: Configure Triggers in MoodleConnect Dashboard

1. Log in to your [MoodleConnect dashboard](https://moodleconnect.com)
2. Navigate to your site
3. Go to the **Connections** tab and add your external tool credentials (e.g., Airtable API key)
4. Go to the **Triggers** tab and create triggers:
   - Select an event (e.g., "User Created")
   - Choose a destination tool (e.g., Airtable)
   - Map event fields to tool fields (e.g., `user.email` → `Email` column)
5. Save your trigger

Now when events occur in Moodle, they'll automatically flow to your configured tools!

## Usage Examples

### Example 1: Sync New Users to Airtable

**Goal**: Automatically add new Moodle users to an Airtable base for CRM tracking.

1. In MoodleConnect plugin settings, enable the `\core\event\user_created` event
2. Sync schemas to MoodleConnect
3. In the MoodleConnect dashboard:
   - Add an Airtable connection with your API key and base ID
   - Create a trigger for "User Created" → Airtable
   - Map fields:
     - `user.firstname` → `First Name`
     - `user.lastname` → `Last Name`
     - `user.email` → `Email`
     - `user.timecreated` → `Registration Date`

### Example 2: Track Course Completions

**Goal**: Log course completions to a webhook for custom processing.

1. Enable `\core\event\course_completed` in plugin settings
2. Sync schemas
3. In MoodleConnect dashboard:
   - Add a Webhook connection with your endpoint URL
   - Create a trigger for "Course Completed" → Webhook
   - Map relevant fields (user info, course name, completion date)

### Example 3: Monitor Assignment Submissions

**Goal**: Get real-time notifications when students submit assignments.

1. Enable `\mod_assign\event\assessable_submitted`
2. Sync schemas
3. Configure a trigger to send submission data to your preferred tool

## Troubleshooting

### Connection Issues

**Problem**: "Failed to connect to MoodleConnect" error

**Solutions**:
- Verify your Moodle site can reach `https://moodleconnect.com` (check firewall rules)
- Ensure your Moodle site has a valid SSL certificate
- Check that your site URL is correctly configured in Moodle settings
- Try disconnecting and reconnecting via the plugin settings page

### Events Not Being Sent

**Problem**: Events are enabled but not appearing in MoodleConnect

**Solutions**:
- Enable **Debug Mode** in plugin settings to see detailed logs
- Check the debug log at `moodledata/moodleconnect_debug.log`
- Verify the event is actually occurring in Moodle (trigger a test event)
- Ensure you've synced schemas after enabling new events
- Check that your triggers are properly configured in the MoodleConnect dashboard

### Schema Sync Failures

**Problem**: Schema sync page shows errors

**Solutions**:
- Verify your site is connected (green status in plugin settings)
- Check that the events you're trying to sync are enabled in settings
- Try syncing one event at a time to identify problematic events
- Check Moodle error logs for PHP errors

### Debug Mode

Enable debug mode to troubleshoot issues:

1. Navigate to **Site administration → Plugins → Local plugins → MoodleConnect**
2. Check **Enable Debug Mode**
3. Click **Save changes**
4. Reproduce the issue
5. Check the log file at `moodledata/moodleconnect_debug.log`

The debug log shows:
- Connection attempts and responses
- Event captures and payloads
- Schema sync operations
- API communication details

**Remember to disable debug mode in production** as it can generate large log files.

## Privacy and Data Handling

This plugin transmits event data from your Moodle site to the MoodleConnect service. The data transmitted depends on which events you enable and may include:

- User information (names, emails, IDs)
- Course information (names, IDs, categories)
- Activity data (submissions, grades, completions)
- Timestamps and metadata

**Data Security**:
- All communication uses HTTPS encryption
- Authentication uses HMAC signatures
- You control exactly which events are monitored
- Data is only sent to tools you explicitly configure

For full privacy details, see the [MoodleConnect Privacy Policy](https://moodleconnect.com/privacy).

## Support and Documentation

- **MoodleConnect Dashboard**: [moodleconnect.com](https://moodleconnect.com)
- **Documentation**: [moodleconnect.com/docs](https://moodleconnect.com/docs)
- **Issue Tracker**: [GitHub Issues](https://github.com/kcanakdag/moodle-local_mc_plugin/issues)
- **Email Support**: support@moodleconnect.com

## Code Quality

This plugin is **fully compliant with Moodle coding standards**:

- ✅ Zero phpcs errors and warnings
- ✅ All variables follow Moodle naming conventions (no underscores)
- ✅ Proper inline comment formatting
- ✅ Complete PHPDoc documentation
- ✅ Line length limits respected (132/180 characters)
- ✅ Proper error handling documentation
- ✅ Moodle-approved function usage

The plugin has been validated using the official [moodle-plugin-ci](https://github.com/moodlehq/moodle-plugin-ci) tool and passes all code quality checks required for the Moodle Plugin Directory.

## Contributing

We welcome contributions! If you'd like to contribute:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

Please ensure your code follows [Moodle coding standards](https://moodledev.io/general/development/policies/codingstyle). Run phpcs before submitting:

```bash
/path/to/moodle-plugin-ci/bin/moodle-plugin-ci phpcs .
```

## License

This plugin is licensed under the [GNU GPL v3](LICENSE).

## About MoodleConnect

MoodleConnect is a micro-SaaS platform that bridges the gap between Moodle LMS and external tools. It enables administrators to create powerful automations without writing code, making it easy to:

- Sync student data to CRMs and spreadsheets
- Trigger notifications and workflows
- Generate custom reports
- Integrate with third-party services

Learn more at [moodleconnect.com](https://moodleconnect.com).

## Changelog

See [CHANGES.md](CHANGES.md) for version history and release notes.

---

**Made with ❤️ for the Moodle community**
