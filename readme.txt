=== EA Gaming Engine ===
Contributors: honorswp, easilyamused
Donate link: https://honorswp.com/donate
Tags: learndash, gamification, education, games, quiz, learning, lms, interactive, engagement, ai
Requires at least: 5.9
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Transform LearnDash courses into interactive educational games with dynamic question gates, themed experiences, and AI-powered hints.

== Description ==

**EA Gaming Engine** revolutionizes online learning by transforming your LearnDash courses into engaging, interactive educational games. Instead of traditional quizzes, students face dynamic challenges through three immersive game modes, each designed to make learning memorable and fun.

### 🎮 Three Engaging Game Modes

**Whack-a-Question**: Fast-paced reflex game where students must quickly identify correct answers as they pop up on screen. Perfect for vocabulary, definitions, and quick recall exercises.

**Tic-Tac-Tactics**: Strategic game combining classic tic-tac-toe with educational content. Students must answer questions correctly to claim squares, adding tactical thinking to knowledge assessment.

**Target Trainer**: Precision-based game where students aim and shoot at correct answers. Ideal for multiple choice questions and improving focus under pressure.

### 🚀 Key Features

**🎯 Seamless LearnDash Integration**
- Automatically converts LearnDash quizzes into interactive games
- Maps courses to game worlds, lessons to quests, and quizzes to boss battles
- Preserves existing course structure while adding gamified elements
- Syncs progress between games and LearnDash completion tracking

**🛡️ Advanced Policy System**
Control when and how students can play with six powerful policy types:
- **Free Play**: Unrestricted access for casual learning
- **Quiet Hours**: Prevent gaming during study or sleep times
- **Study First**: Require lesson completion before game access
- **Parent Control**: Integration with parent oversight systems
- **Daily Limits**: Set maximum daily play time per student
- **Course Specific**: Customize rules for individual courses

**🎨 Beautiful Themes & Presets**
- **Playful Theme**: Vibrant colors perfect for younger learners
- **Minimal Pro**: Clean, professional aesthetic for adult education
- **Neon Cyber**: Futuristic design for tech-focused courses
- Four difficulty presets: Chill, Classic, Pro, and Accessible modes

**🤖 AI-Powered Learning Assistant**
- Context-aware hint system analyzes lesson content
- Provides personalized suggestions when students struggle
- Adaptive difficulty based on performance patterns
- Smart remediation recommendations

**📊 Comprehensive Analytics**
- Detailed player statistics and progress tracking
- Course-wide leaderboards to encourage friendly competition
- Session analytics showing time spent and questions answered
- Performance insights for educators and administrators

**🔧 Developer-Friendly Architecture**
- RESTful API with 20+ endpoints for custom integrations
- Webhook support for external learning management systems
- Modular design allowing custom game development
- Extensive filter and action hooks for customization

### 🎯 Perfect For:

- **Educational Institutions**: Make curriculum more engaging with gamified assessments
- **Corporate Training**: Transform boring compliance training into interactive experiences  
- **Online Course Creators**: Differentiate your courses with unique gaming elements
- **Tutoring Services**: Provide fun, measurable practice sessions for students
- **Homeschool Parents**: Add game-based learning to supplement traditional materials

### 📱 Available Shortcodes

Display EA Gaming Engine components anywhere on your site:

- `[ea_gaming_arcade]` - Full game arcade with course selection
- `[ea_gaming_launcher]` - Launch button for specific courses
- `[ea_gaming_leaderboard]` - Course or site-wide leaderboards
- `[ea_gaming_stats]` - Player statistics and achievements

### 🎯 Gutenberg Blocks

Native WordPress blocks for seamless page building:
- EA Gaming Arcade Block
- Game Launcher Block  
- Leaderboard Block
- Player Stats Block

### 🔐 Security & Performance

- Server-side answer validation prevents cheating
- Rate limiting on API endpoints
- Optimized caching for fast question delivery
- User session management with proper authentication
- GDPR-compliant data handling

### 🌍 Accessibility & Internationalization

- WCAG 2.1 AA compliant interface design
- Full translation support with included .pot file
- High contrast options for visually impaired users
- Keyboard navigation support
- Screen reader compatible

### 🔗 Integrations

**Required:**
- LearnDash LMS (tested with latest versions)

**Optional:**
- EA Student-Parent Access (for enhanced parental controls)
- EA Flashcards (for remediation activities)
- Popular LMS plugins via webhook system

### 💡 Use Cases

**K-12 Education**: Transform math drills, vocabulary practice, and science facts into engaging games that students actually want to play.

**Higher Education**: Make complex concepts stick with interactive review sessions that complement traditional lectures and readings.

**Corporate Training**: Turn compliance training, product knowledge, and skill assessments into competitive team activities.

**Language Learning**: Practice vocabulary, grammar, and pronunciation through fast-paced, repetitive game mechanics.

**Professional Development**: Reinforce certification material and industry knowledge through strategic gameplay elements.

### 🎮 Getting Started

1. Install and activate LearnDash LMS
2. Install EA Gaming Engine
3. Configure your first game policy in the admin settings
4. Add the `[ea_gaming_arcade]` shortcode to any page
5. Students can immediately start playing games based on your existing LearnDash content

Transform your educational content into an adventure your students will never forget!

== Installation ==

### Automatic Installation

1. Go to your WordPress admin dashboard
2. Navigate to **Plugins > Add New**
3. Search for "EA Gaming Engine"
4. Click **Install Now** and then **Activate**

### Manual Installation

1. Download the plugin zip file
2. Upload the zip file through **Plugins > Add New > Upload Plugin**
3. Activate the plugin through the **Plugins** menu

### Requirements

- WordPress 5.9 or higher
- PHP 7.4 or higher  
- LearnDash LMS plugin (any recent version)
- Modern web browser with JavaScript enabled

### Initial Setup

1. **Configure Policies**: Go to **EA Gaming > Policies** to set up access rules
2. **Choose Theme**: Visit **EA Gaming > Settings** to select your preferred visual theme
3. **Set Difficulty**: Choose from Chill, Classic, Pro, or Accessible presets
4. **Add Games**: Use shortcodes or Gutenberg blocks to display games on your site
5. **Test with Students**: Create a test course and verify game functionality

### Recommended Hosting Requirements

- PHP memory limit: 256MB or higher
- MySQL 5.6+ or MariaDB 10.0+
- SSL certificate for secure session management
- Modern caching solution (Redis/Memcached) for optimal performance

== Frequently Asked Questions ==

= Do I need LearnDash for this plugin to work? =

Yes, EA Gaming Engine is specifically designed to work with LearnDash LMS. It transforms your existing LearnDash quizzes into interactive games, so LearnDash must be installed and activated.

= Will this work with my existing LearnDash courses? =

Absolutely! EA Gaming Engine automatically detects your existing LearnDash courses and converts quiz questions into game challenges. No manual migration required.

= Can students cheat by viewing page source or using browser tools? =

No. All answer validation happens on the server side. Students receive questions without the correct answers, and all validation occurs through secure AJAX requests. Question attempts are logged for administrative review.

= How do the game policies work? =

Policies are rules that control when and how students can play games. For example, you can require students to complete lessons before accessing games, set daily time limits, or restrict gaming during certain hours. Policies are evaluated in priority order and can be course-specific.

= Can I customize the games or create new ones? =

The plugin provides hooks and filters for customization. For advanced customization or new game development, you can use the provided TypeScript framework and Phaser 3 game engine integration.

= What happens to student progress and scores? =

All game sessions, scores, and question attempts are stored in your WordPress database. Progress can be viewed in the admin analytics section, and student achievements are preserved permanently.

= Is the plugin translation-ready? =

Yes! EA Gaming Engine includes a complete .pot file for translation and follows WordPress internationalization standards. The interface is also designed to be WCAG 2.1 AA compliant.

= How does the AI hint system work? =

The AI system analyzes the content of your LearnDash lessons and generates contextual hints when students struggle with questions. Hints are generated server-side and consider the course material to provide relevant assistance.

= Can parents monitor their children's gaming activity? =

Yes, when used with the EA Student-Parent Access plugin, parents can set restrictions, view progress reports, and control gaming access through a dedicated parent dashboard.

= What themes and presets are available? =

Three themes are included: Playful (vibrant colors for younger learners), Minimal Pro (professional design), and Neon Cyber (futuristic aesthetic). Four difficulty presets range from Chill (easier) to Pro (challenging) with an Accessible mode for users with special needs.

= Does this affect my site's loading speed? =

EA Gaming Engine is optimized for performance with efficient caching, lazy loading of game assets, and minimal impact on non-gaming pages. Games only load when actively launched by students.

= Can I use this for corporate training? =

Absolutely! The Minimal Pro theme and Professional preset are specifically designed for corporate learning environments. The policy system allows you to enforce training completion requirements and track employee progress.

= What browsers are supported? =

EA Gaming Engine works on all modern browsers including Chrome, Firefox, Safari, and Edge. Mobile browsers are also supported for responsive gaming experiences.

= How do I display games on my website? =

Use the provided shortcodes like `[ea_gaming_arcade]` for a full game selection interface, or `[ea_gaming_launcher course_id="123"]` for specific course games. Gutenberg blocks are also available for easy page building.

= Can I export student game data? =

Yes, all game data can be exported through the admin analytics section. Data includes session details, scores, time spent, and question attempt history for comprehensive reporting.

== Screenshots ==

1. **Game Arcade Interface** - Students can browse and launch games for their enrolled courses with an intuitive card-based layout showing progress and achievements.

2. **Whack-a-Question Gameplay** - Fast-paced action game where students must quickly identify correct answers as they appear and disappear on screen.

3. **Tic-Tac-Tactics Strategic Play** - Educational twist on classic tic-tac-toe where students must answer questions correctly to claim squares and achieve victory.

4. **Target Trainer Precision Game** - Students aim and shoot at correct answers while avoiding incorrect options in this skill-based challenge.

5. **Admin Dashboard Overview** - Comprehensive analytics showing player statistics, popular games, and overall engagement metrics for administrators.

6. **Policy Management Interface** - Easy-to-use controls for setting up game access rules, time restrictions, and prerequisite requirements.

7. **Theme Customization Panel** - Choose from three beautiful themes and four difficulty presets to match your brand and audience needs.

8. **Leaderboard and Statistics** - Motivate students with course leaderboards and detailed progress tracking showing achievements and areas for improvement.

9. **Mobile-Responsive Gaming** - All games work seamlessly on tablets and smartphones with touch-optimized controls and responsive layouts.

10. **Integration with LearnDash** - Seamless integration showing how course lessons become game worlds and quizzes transform into boss battles.

== Changelog ==

= 1.0.0 =
*Release Date: January 15, 2025*

**Initial Release Features:**

* **Core Game Engine**
  * Three complete games: Whack-a-Question, Tic-Tac-Tactics, and Target Trainer
  * Server-side answer validation system for security
  * Comprehensive session management and progress tracking
  * Real-time scoring and achievement system

* **LearnDash Integration**
  * Automatic course-to-game mapping
  * Quiz question extraction and conversion
  * Progress synchronization between games and LearnDash
  * Course structure preservation (lessons → worlds, quizzes → bosses)

* **Policy Management System**
  * Six policy types: Free Play, Quiet Hours, Study First, Parent Control, Daily Limits, Course Specific
  * Priority-based policy evaluation
  * Flexible conditions and actions system
  * Course-specific rule customization

* **Themes and Presets**
  * Three visual themes: Playful, Minimal Pro, Neon Cyber
  * Four difficulty presets: Chill, Classic, Pro, Accessible
  * Customizable color palettes and UI elements
  * Accessibility features including high contrast options

* **AI Hint System**
  * Context-aware hint generation
  * Lesson content analysis for relevant suggestions
  * Adaptive difficulty based on student performance
  * Smart remediation recommendations

* **Analytics and Reporting**
  * Detailed player statistics per course
  * Site-wide and course-specific leaderboards
  * Session analytics with time tracking
  * Question attempt logging and analysis

* **Frontend Features**
  * Four shortcodes for flexible content placement
  * Native Gutenberg blocks integration
  * Mobile-responsive design
  * Auto-insertion on LearnDash content pages

* **Admin Interface**
  * Complete administrative dashboard
  * Settings management with real-time preview
  * Policy configuration interface
  * Analytics and reporting tools

* **REST API**
  * 20+ endpoints for complete functionality
  * Session management and game launching
  * Policy checking and enforcement
  * Statistics and leaderboard data access

* **Developer Features**
  * Comprehensive hook system for customization
  * TypeScript framework for game development
  * Modular architecture for easy extension
  * PSR-4 autoloading and WordPress coding standards

* **Security and Performance**
  * Server-side validation prevents cheating
  * Optimized caching system
  * Rate limiting on API endpoints
  * GDPR-compliant data handling

* **Accessibility and Internationalization**
  * WCAG 2.1 AA compliance
  * Complete .pot file for translations
  * Keyboard navigation support
  * Screen reader compatibility

== Upgrade Notice ==

= 1.0.0 =
Initial release of EA Gaming Engine. Transform your LearnDash courses into engaging educational games with three unique game modes, advanced policy management, and comprehensive analytics. Requires LearnDash LMS and WordPress 5.9+.