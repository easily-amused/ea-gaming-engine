/**
 * Admin JavaScript Entry Point
 */

// Import styles
import '../../css/admin.css';

// Import modules
import './settings';
import './dashboard';
import './analytics';
import './games';
import './policies';

// Initialize admin when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  console.log('EA Gaming Engine Admin initialized');
});