# MoodleConnect Plugin

[![Moodle Plugin](https://img.shields.io/badge/Moodle-4.0%2B-orange.svg)](https://moodle.org)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-GPL%20v3-blue.svg)](LICENSE)

The official Moodle plugin for [MoodleConnect](https://moodleconnect.com) — a platform that bridges Moodle LMS events to external tools like Airtable, webhooks, and more.

## Overview

MoodleConnect enables Moodle administrators to automatically sync events (user creation, course enrollment, assignment submissions, etc.) to external services for reporting, automation, and data management — without writing any code.

**Key feature**: One-click connection with automatic event configuration. No manual event selection needed!

## Features

- **One-Click Connection**: Secure OAuth-style flow connects your site instantly
- **Automatic Event Sync**: Events flow based on your dashboard triggers — no manual selection
- **Reverse Sync**: MoodleConnect tells the plugin which events to capture
- **Course Filtering**: Filter events by course at the source
- **Secure Communication**: HMAC-SHA256 authentication for all requests
- **Debug Mode**: Built-in logging for troubleshooting
- **Zero Performance Impact**: Asynchronous event handling

## Requirements

- **Moodle**: 4.0 or higher
- **PHP**: 7.4 or higher
- **MoodleConnect Account**: Free at [moodleconnect.com](https://moodleconnect.com)

## Installation

### Via Moodle Plugin Installer (Recommended)

1. Download the latest release from [GitHub Releases](https://github.com/kcanakdag/moodle-local_mc_plugin/releases)
2. In Moodle: **Site administration → Plugins → Install plugins**
3. Upload the ZIP file and follow the prompts

### Manual Installation

1. Download and extract the release ZIP
2. Upload to `moodle/local/mc_plugin/`
3. Visit **Site administration → Notifications** to complete installation

### Via Git

```bash
cd /path/to/moodle/local
git clone https://github.com/kcanakdag/moodle-local_mc_plugin.git mc_plugin
```

## Quick Start

### 1. Connect Your Site

1. Go to **Site administration → Plugins → Local plugins → MoodleConnect**
2. Click **Connect to MoodleConnect**
3. Log in and authorize the connection
4. Done! Your site is connected.

### 2. Create Triggers (in MoodleConnect Dashboard)

1. Log in to [moodleconnect.com](https://moodleconnect.com)
2. Go to **Connections** tab — add your Airtable or webhook credentials
3. Go to **Triggers** tab — create automations:
   - Select an event (e.g., "User Created")
   - Choose a destination tool
   - Map fields (e.g., `{{user.email}}` → `Email`)
4. Save — events automatically start flowing!

That's it! The plugin automatically knows which events to send based on your triggers.

## How It Works

```
┌─────────────────┐     ┌──────────────────┐     ┌─────────────────┐
│   Moodle LMS    │◀───▶│  MoodleConnect   │────▶│  External Tools │
└─────────────────┘     └──────────────────┘     └─────────────────┘
        ▲                       │
        │    Reverse Sync       │
        └───────────────────────┘
```

**Reverse Sync**: When you create a trigger in MoodleConnect, it tells your Moodle plugin which events to capture. No manual configuration needed!

## Troubleshooting

### Enable Debug Mode

1. In plugin settings, check **Enable Debug Mode**
2. Trigger an event in Moodle
3. Check `moodledata/moodleconnect_debug.log`

### Common Issues

| Issue | Solution |
|-------|----------|
| Events not sending | Check connection status, verify triggers exist |
| "Plugin update required" | Update to v5.0.0 or higher |
| Connection failed | Check firewall allows outbound HTTPS |

See the [Troubleshooting Guide](https://github.com/kcanakdag/moodle-local_mc_plugin/wiki/Troubleshooting) for more.

## Privacy

This plugin transmits event data to MoodleConnect based on your trigger configuration. Only events with active triggers are sent.

- All communication uses HTTPS
- HMAC signatures verify authenticity
- You control what data is transmitted

See [Privacy & GDPR](https://github.com/kcanakdag/moodle-local_mc_plugin/wiki/Privacy) for details.

## Documentation

- **[Installation](https://github.com/kcanakdag/moodle-local_mc_plugin/wiki/Installation)**
- **[Configuration](https://github.com/kcanakdag/moodle-local_mc_plugin/wiki/Configuration)**
- **[Events](https://github.com/kcanakdag/moodle-local_mc_plugin/wiki/Events)**
- **[Triggers](https://github.com/kcanakdag/moodle-local_mc_plugin/wiki/Triggers)**
- **[FAQ](https://github.com/kcanakdag/moodle-local_mc_plugin/wiki/FAQ)**

## Support

- **Issues**: [GitHub Issues](https://github.com/kcanakdag/moodle-local_mc_plugin/issues)
- **Email**: kerem@moodleconnect.com

## Contributing

1. Fork the repository
2. Create a feature branch
3. Ensure code follows [Moodle coding standards](https://moodledev.io/general/development/policies/codingstyle)
4. Submit a Pull Request

## License

GNU GPL v3 — see [LICENSE](LICENSE)

---

**Made with ❤️ for the Moodle community**
