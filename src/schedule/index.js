import { createRoot } from '@wordpress/element';
import App from './App';
import './schedule.css';
import './notifications.css';

const root = document.getElementById('lynxjournal-schedule-root');
if (root) {
  createRoot(root).render(<App />);
}
