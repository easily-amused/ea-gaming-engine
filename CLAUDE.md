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

Key endpoints (to be implemented in REST/RestAPI.php):
- `/questions/{quiz_id}` - Get questions for a quiz
- `/validate-answer` - Server-side answer validation
- `/policies/active` - Get active policies for current user
- `/stats/session` - Update/retrieve session statistics

### Theme System

Two built-in themes with configurable color palettes:
- **Playful**: Vibrant colors for younger audiences
- **Minimal Pro**: Professional aesthetic for adult learners

Three profile presets control difficulty and effects:
- **Chill**: 0.8x speed, easy AI, hints enabled
- **Classic**: 1.0x speed, medium AI, no hints
- **Pro**: 1.5x speed, hard AI, minimal effects

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
- Game assets in `assets/games/`

### Coding Standards
- WordPress Coding Standards enforced via PHPCS
- PSR-4 autoloading for PHP classes
- TypeScript for game development
- React for admin interfaces
- Phaser 3.70 for game engine

## Current Implementation Status

### Completed
- Core plugin structure and initialization
- Database schema and activation/deactivation
- GameEngine with session management
- QuestionGate with server-side validation
- PolicyEngine with all policy types
- Basic LearnDash integration scaffolding

### Pending Implementation
- ThemeManager class for theme switching
- Full LearnDash integration mapping
- REST API controllers
- Admin settings interface
- Frontend game launcher
- Gutenberg block registration
- Three launch games (Whack-a-Question, Tic-Tac-Tactics, Target Trainer)
- Webpack configuration for asset bundling
- TypeScript/Phaser game framework

## Testing Approach

When implementing tests:
- Unit tests for policy evaluation logic
- Integration tests for LearnDash question fetching
- Mock WpProQuiz_Model classes for testing
- Test database operations with WordPress test factory
- Frontend game testing with Phaser test utilities