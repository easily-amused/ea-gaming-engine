# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

EA Gaming Engine is a WordPress plugin that transforms LearnDash courses into interactive educational games. It integrates with LearnDash LMS to create gamified learning experiences with dynamic question gates, themed gameplay, and policy-based access controls.

## Development Commands

### Initial Setup
```bash
composer install                 # Install PHP dependencies including EA licensing package
npm install                      # Install Node dependencies for build tools
```

### Build Commands
```bash
npm run build                    # Production build of JavaScript/CSS assets
npm run dev                      # Development build with watch mode
npm run build:games             # Build Phaser game assets
npm run dev:games               # Development mode for game assets
```

### Code Quality
```bash
composer run phpcs               # Check PHP coding standards
composer run phpcbf              # Auto-fix PHP coding standard violations
composer run php-check           # Check PHP compatibility (7.4+)
npm run lint                     # Lint JavaScript/TypeScript files
npm run lint:fix                 # Auto-fix JavaScript/TypeScript issues
```

## Architecture

### Core Systems

**Plugin Initialization Flow**
- `ea-gaming-engine.php` → Requirements check → `Plugin::get_instance()` → Component initialization
- All components are instantiated through the main `Plugin` class singleton
- Database tables are created on activation via `Activator::activate()`

**Game Session Lifecycle**
1. PolicyEngine validates user access (`can_user_play()`)
2. GameEngine creates session record (`start_session()`)
3. QuestionGate serves questions with server-side validation
4. GameEngine tracks progress and updates stats (`end_session()`)

**Question Validation Architecture**
- Questions are cached server-side with answers for 5 minutes
- Frontend receives questions without correct answers
- Validation happens server-side via AJAX to prevent cheating
- Question attempts are logged in `ea_question_attempts` table

**Policy Rules System**
- Policies evaluated in priority order (lower number = higher priority)
- Each policy returns block/allow with optional message
- Policy types: free_play, quiet_hours, study_first, parent_control, daily_limit, course_specific
- Policies stored in `ea_game_policies` table with JSON conditions/actions

### Database Schema

Four custom tables manage game data:
- `ea_game_sessions`: Game session tracking with user, course, scores
- `ea_game_policies`: Policy rules with JSON conditions and actions
- `ea_question_attempts`: Individual question attempts per session
- `ea_player_stats`: Aggregated player statistics per course

### Integration Points

**LearnDash Integration**
- Dynamically pulls questions from LearnDash quizzes via `learndash_get_quiz_questions()`
- Maps courses → games, lessons → quests, quizzes → boss battles
- Uses WpProQuiz_Model_QuestionMapper for question data

**EA Licensing**
- Uses `EA\Licensing\License` from easily-amused/php-packages
- Product ID placeholder: 9999 (needs updating for production)

**Parent-Student Access Integration**
- Hooks into `ea_gaming_parent_controls` filter
- Checks for ticket requirements and time restrictions
- Integrates with EA Student-Parent Access plugin when available

### REST API Endpoints

Base URL: `/wp-json/ea-gaming/v1/`

Key endpoints implemented:
- `/sessions` - Create, retrieve game sessions
- `/sessions/{id}` - Get, update, end specific session
- `/questions/{quiz_id}` - Get questions for a quiz
- `/validate-answer` - Server-side answer validation
- `/policies/active` - Get active policies for current user
- `/policies/check` - Check if user can play
- `/stats/player` - Get player statistics
- `/stats/leaderboard` - Get leaderboard data
- `/themes` - Get all themes, current theme, set theme
- `/presets` - Get all presets, current preset, set preset
- `/courses/{id}/structure` - Get course game structure
- `/games/available` - List available games
- `/games/launch` - Launch a game with policy checks

### Theme System

Three built-in themes with configurable color palettes:
- **Playful**: Vibrant colors for younger audiences
- **Minimal Pro**: Professional aesthetic for adult learners
- **Neon Cyber**: Futuristic cyberpunk theme

Four profile presets control difficulty and effects:
- **Chill**: 0.8x speed, easy AI, hints enabled
- **Classic**: 1.0x speed, medium AI, no hints
- **Pro**: 1.5x speed, hard AI, minimal effects
- **Accessible**: 0.6x speed, high contrast, larger text

### Security Considerations

- All answer validation happens server-side
- Questions cached with user-specific transients
- Nonces required for all AJAX operations
- Rate limiting should be implemented on API endpoints
- Session IDs validated against current user

## Plugin Conventions

### Naming Patterns
- PHP namespaces: `EAGamingEngine\{Component}\{Class}`
- Database tables: `{prefix}_ea_game_{table}`
- Options: `ea_gaming_engine_{option}`
- Hooks: `ea_gaming_{action/filter}`
- Text domain: `ea-gaming-engine`

### File Organization
- Core logic in `includes/Core/`
- Integrations in `includes/Integrations/`
- REST controllers in `includes/REST/`
- Admin code in `includes/Admin/`
- Frontend code in `includes/Frontend/`
- Blocks code in `includes/Blocks/`
- Game assets in `assets/games/`
- JavaScript source in `assets/js/`
- Compiled assets in `assets/dist/`

### Coding Standards
- WordPress Coding Standards enforced via PHPCS
- PSR-4 autoloading for PHP classes
- TypeScript for game development
- React for admin interfaces
- Phaser 3.70 for game engine

## Current Implementation Status

### ✅ Completed Components
- **Core Infrastructure**
  - Plugin initialization and activation/deactivation
  - Database schema with 4 custom tables
  - Plugin class with component orchestration
  
- **Game Systems**
  - GameEngine with session management and player stats
  - QuestionGate with server-side validation and hint generation
  - PolicyEngine with 6 policy types (free_play, quiet_hours, study_first, etc.)
  - ThemeManager with 3 themes and 4 presets
  
- **Integrations**
  - Full LearnDash integration with course/quiz mapping
  - Course structure conversion (lessons→worlds, quizzes→boss battles)
  - Progress synchronization between game and LearnDash
  
- **REST API**
  - Complete REST API with 20+ endpoints
  - Session management endpoints
  - Question/answer validation
  - Policy checking and enforcement
  - Stats and leaderboard endpoints
  
- **Admin Interface**
  - Admin menu structure with 5 pages (Dashboard, Settings, Policies, Analytics, Games)
  - Settings management with AJAX handlers
  - Analytics data retrieval
  
- **Frontend**
  - Frontend class with game launcher UI
  - Shortcodes: [ea_gaming_arcade], [ea_gaming_launcher], [ea_gaming_leaderboard], [ea_gaming_stats]
  - Game modal structure
  - Auto-insertion on LearnDash content
  
- **Blocks**
  - Gutenberg blocks registered for arcade, launcher, leaderboard, and stats
  - Block category added for EA Gaming Engine
  
- **Build System**
  - Webpack configuration for TypeScript, React, SCSS
  - Asset optimization and code splitting

### 🚧 In Progress / Pending

**High Priority - Core Functionality**
1. **Game Implementation** (Critical for MVP)
   - TypeScript/Phaser game framework base structure
   - Whack-a-Question game implementation
   - Tic-Tac-Tactics game implementation
   - Target Trainer game implementation
   - Game-to-API communication layer

2. **Asset Creation**
   - JavaScript entry files (admin/index.js, frontend/index.js, blocks/index.js)
   - React components for admin settings interface
   - CSS/SCSS files for styling
   - Game sprites and audio assets

3. **AI Features**
   - NPC hint system implementation with lesson context
   - Dynamic hint generation based on course content

**Medium Priority - Enhanced Features**
4. **Integration Enhancements**
   - Student-Parent Access integration hooks
   - Flashcards integration for remediation quests
   - Ticket/economy system base implementation

5. **Documentation & Deployment**
   - README.txt for WordPress.org format
   - i18n support and .pot file generation
   - GitHub Actions workflow for CI/CD
   - uninstall.php for clean removal

6. **Content & Testing**
   - Demo course content creation
   - Comprehensive testing suite
   - Bug fixes and optimization

### Next Immediate Steps

1. **Create JavaScript entry files** for webpack compilation
2. **Implement TypeScript/Phaser game framework** base structure
3. **Build first game (Whack-a-Question)** as proof of concept
4. **Create React admin components** for settings management
5. **Style the frontend** with CSS/SCSS
6. **Test with LearnDash** course content

## Testing Approach

When implementing tests:
- Unit tests for policy evaluation logic
- Integration tests for LearnDash question fetching
- Mock WpProQuiz_Model classes for testing
- Test database operations with WordPress test factory
- Frontend game testing with Phaser test utilities

## Common Tasks

### Adding a New Game Type
1. Add game type to `GameEngine::$game_types` array
2. Create game class in `assets/games/src/games/`
3. Register in game factory
4. Add configuration to admin settings
5. Create game card in frontend

### Adding a New Policy Type
1. Add to `PolicyEngine::$policy_types` array
2. Create evaluation method `evaluate_{type}()`
3. Add admin UI for policy configuration
4. Document policy behavior

### Creating a New Theme
1. Add theme definition to `ThemeManager::init_themes()`
2. Define color palette, fonts, animations, sounds, UI settings
3. Create theme-specific CSS in `get_theme_specific_styles()`
4. Add Google Fonts mapping if needed

## Repository Information

- **GitHub**: https://github.com/easily-amused/ea-gaming-engine
- **Organization**: easily-amused
- **License**: GPL-2.0+
- **Requires**: PHP 7.4+, WordPress 5.9+, LearnDash
- Add to memory