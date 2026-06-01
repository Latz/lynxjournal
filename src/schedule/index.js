import { createRoot } from '@wordpress/element';
import App from './App';
import './schedule.css';

const root = document.getElementById('linkdigest-schedule-root');
if (root) {
  createRoot(root).render(<App />);
}
