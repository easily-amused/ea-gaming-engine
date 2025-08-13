# EA Gaming Engine

[![CI](https://github.com/easily-amused/ea-gaming-engine/workflows/CI/badge.svg)](https://github.com/easily-amused/ea-gaming-engine/actions/workflows/ci.yml)
[![Release](https://github.com/easily-amused/ea-gaming-engine/workflows/Release/badge.svg)](https://github.com/easily-amused/ea-gaming-engine/actions/workflows/release.yml)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-5.9%2B-blue.svg)](https://wordpress.org)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg)](LICENSE)
[![Latest Release](https://img.shields.io/github/v/release/easily-amused/ea-gaming-engine)](https://github.com/easily-amused/ea-gaming-engine/releases)

Transform LearnDash courses into interactive educational games with dynamic question gates, themed experiences, and AI-powered hints.

## Overview

EA Gaming Engine is a WordPress plugin that revolutionizes online learning by transforming your LearnDash courses into engaging, interactive educational games. Instead of traditional quizzes, students face dynamic challenges through three immersive game modes.

### Game Modes

- **Whack-a-Question**: Fast-paced reflex game for quick recall exercises
- **Tic-Tac-Tactics**: Strategic game combining classic tic-tac-toe with educational content
- **Target Trainer**: Precision-based game for multiple choice questions

### Key Features

- 🎯 **Seamless LearnDash Integration** - Automatic course-to-game mapping
- 🛡️ **Advanced Policy System** - Six policy types for access control
- 🎨 **Beautiful Themes & Presets** - Three themes, four difficulty levels
- 🤖 **AI-Powered Hints** - Context-aware learning assistance
- 📊 **Comprehensive Analytics** - Detailed progress tracking and leaderboards
- 🔧 **Developer-Friendly** - REST API with 20+ endpoints

## Requirements

- WordPress 5.9 or higher
- PHP 7.4 or higher
- LearnDash LMS plugin
- Modern web browser with JavaScript enabled

## Installation

### Via WordPress Admin

1. Download the latest release from [GitHub Releases](https://github.com/easily-amused/ea-gaming-engine/releases)
2. Upload the ZIP file through **Plugins > Add New > Upload Plugin**
3. Activate the plugin through the **Plugins** menu

### Manual Installation

1. Extract the plugin files to `/wp-content/plugins/ea-gaming-engine/`
2. Activate the plugin through the **Plugins** menu in WordPress

## Development

### Setup

```bash
# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install
```

### Build Commands

```bash
# Development build with watch mode
npm run dev

# Production build
npm run build

# Build game assets
npm run build:games

# Development mode for games
npm run dev:games
```

### Code Quality

```bash
# PHP coding standards check
composer run phpcs

# Auto-fix PHP coding standards
composer run phpcbf

# PHP compatibility check
composer run php-check

# JavaScript/TypeScript linting
npm run lint

# Auto-fix JS/TS issues
npm run lint:fix
```

## Usage

### Shortcodes

- `[ea_gaming_arcade]` - Full game arcade with course selection
- `[ea_gaming_launcher]` - Launch button for specific courses
- `[ea_gaming_leaderboard]` - Course or site-wide leaderboards
- `[ea_gaming_stats]` - Player statistics and achievements

### Gutenberg Blocks

- EA Gaming Arcade Block
- Game Launcher Block
- Leaderboard Block
- Player Stats Block

## Configuration

1. **Configure Policies**: Go to **EA Gaming > Policies** to set up access rules
2. **Choose Theme**: Visit **EA Gaming > Settings** to select your visual theme
3. **Set Difficulty**: Choose from Chill, Classic, Pro, or Accessible presets
4. **Add Games**: Use shortcodes or Gutenberg blocks to display games

## API Documentation

The plugin provides a comprehensive REST API with endpoints for:

- Session management (`/wp-json/ea-gaming/v1/sessions`)
- Question validation (`/wp-json/ea-gaming/v1/validate-answer`)
- Policy checking (`/wp-json/ea-gaming/v1/policies/check`)
- Statistics (`/wp-json/ea-gaming/v1/stats/player`)
- Leaderboards (`/wp-json/ea-gaming/v1/stats/leaderboard`)

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Development Guidelines

- Follow WordPress Coding Standards
- Use PSR-4 autoloading for PHP classes
- Write TypeScript for game development
- Include tests for new functionality
- Update documentation as needed

## Security

- Server-side answer validation prevents cheating
- Rate limiting on API endpoints
- Proper input sanitization and validation
- GDPR-compliant data handling

For security issues, please email security@honorswp.com instead of opening a public issue.

## Support

- **Documentation**: [HonorsWP EA Gaming Engine](https://honorswp.com/ea-gaming-engine/)
- **Support**: [HonorsWP Support](https://honorswp.com/support/)
- **Issues**: [GitHub Issues](https://github.com/easily-amused/ea-gaming-engine/issues)

## License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a full list of changes and version history.

---

**Made with ❤️ by [HonorsWP](https://honorswp.com)**