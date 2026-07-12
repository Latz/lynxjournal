import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import apiFetch from '@wordpress/api-fetch';
import App from '../../../src/tags/App.jsx';

const TAGS = [
    { id: 1, name: 'Tech', slug: 'tech', description: 'Tech stuff' },
    { id: 2, name: 'News', slug: 'news', description: '' },
];
const COUNTS = { 1: 3, 2: 0 };

function mockTagsAndCounts(tags = TAGS, counts = COUNTS) {
    apiFetch.mockImplementation(({ path }) => {
        if (path === '/lynxjournal/v1/tags') {
            return Promise.resolve(tags);
        }
        if (path === '/lynxjournal/v1/tags/counts') {
            return Promise.resolve(counts);
        }
        return Promise.resolve({});
    });
}

describe('Tags App', () => {
    beforeEach(() => {
        apiFetch.mockReset();
        apiFetch.mockImplementation(() => Promise.resolve({}));
        vi.spyOn(window, 'confirm').mockReturnValue(true);
    });

    it('loads tags and counts on mount and renders the table', async () => {
        mockTagsAndCounts();
        render(<App />);

        expect(await screen.findByText('Tech')).toBeInTheDocument();
        expect(screen.getByText('News')).toBeInTheDocument();
        expect(screen.getByText('3')).toBeInTheDocument();
    });

    it('shows the empty-state message when there are no tags', async () => {
        mockTagsAndCounts([], {});
        render(<App />);

        expect(await screen.findByText('No tags yet. Use the form to add your first tag.')).toBeInTheDocument();
    });

    it('enters edit mode and saves changes', async () => {
        mockTagsAndCounts();
        apiFetch.mockImplementation(({ path, method }) => {
            if (path === '/lynxjournal/v1/tags') return Promise.resolve(TAGS);
            if (path === '/lynxjournal/v1/tags/counts') return Promise.resolve(COUNTS);
            if (path === '/lynxjournal/v1/tags/1' && method === 'POST') {
                return Promise.resolve({ id: 1, name: 'Technology', slug: 'tech', description: 'Tech stuff' });
            }
            return Promise.resolve({});
        });
        const user = userEvent.setup();
        render(<App />);

        await screen.findByText('Tech');
        await user.click(screen.getAllByRole('button', { name: 'Edit' })[0]);

        const nameInput = screen.getByDisplayValue('Tech');
        await user.clear(nameInput);
        await user.type(nameInput, 'Technology');
        await user.click(screen.getByRole('button', { name: 'Save' }));

        expect(await screen.findByText('Technology')).toBeInTheDocument();
        expect(apiFetch).toHaveBeenCalledWith(expect.objectContaining({
            path: '/lynxjournal/v1/tags/1',
            method: 'POST',
        }));
    });

    it('shows a validation error when saving an empty name', async () => {
        mockTagsAndCounts();
        const user = userEvent.setup();
        render(<App />);

        await screen.findByText('Tech');
        await user.click(screen.getAllByRole('button', { name: 'Edit' })[0]);

        const nameInput = screen.getByDisplayValue('Tech');
        await user.clear(nameInput);
        await user.click(screen.getByRole('button', { name: 'Save' }));

        expect(await screen.findByText('Name is required.')).toBeInTheDocument();
    });

    it('cancels edit mode without saving', async () => {
        mockTagsAndCounts();
        const user = userEvent.setup();
        render(<App />);

        await screen.findByText('Tech');
        await user.click(screen.getAllByRole('button', { name: 'Edit' })[0]);
        await user.click(screen.getByRole('button', { name: 'Cancel' }));

        expect(screen.getByText('Tech')).toBeInTheDocument();
        expect(screen.queryByDisplayValue('Tech')).not.toBeInTheDocument();
    });

    it('deletes a tag after confirming', async () => {
        mockTagsAndCounts();
        apiFetch.mockImplementation(({ path, method }) => {
            if (path === '/lynxjournal/v1/tags') return Promise.resolve(TAGS);
            if (path === '/lynxjournal/v1/tags/counts') return Promise.resolve(COUNTS);
            if (path === '/lynxjournal/v1/tags/1' && method === 'DELETE') return Promise.resolve(null);
            return Promise.resolve({});
        });
        const user = userEvent.setup();
        render(<App />);

        await screen.findByText('Tech');
        await user.click(screen.getAllByRole('button', { name: 'Delete' })[0]);

        await waitFor(() => expect(screen.queryByText('Tech')).not.toBeInTheDocument());
        expect(window.confirm).toHaveBeenCalled();
    });

    it('does not delete when the confirm dialog is dismissed', async () => {
        mockTagsAndCounts();
        window.confirm.mockReturnValue(false);
        const user = userEvent.setup();
        render(<App />);

        await screen.findByText('Tech');
        await user.click(screen.getAllByRole('button', { name: 'Delete' })[0]);

        expect(screen.getByText('Tech')).toBeInTheDocument();
        expect(apiFetch).not.toHaveBeenCalledWith(expect.objectContaining({ method: 'DELETE' }));
    });

    it('creates a new tag via the add-tag form', async () => {
        mockTagsAndCounts();
        apiFetch.mockImplementation(({ path, method }) => {
            if (path === '/lynxjournal/v1/tags' && method === 'POST') {
                return Promise.resolve({ id: 3, name: 'Design', slug: 'design', description: '' });
            }
            if (path === '/lynxjournal/v1/tags') return Promise.resolve(TAGS);
            if (path === '/lynxjournal/v1/tags/counts') return Promise.resolve(COUNTS);
            return Promise.resolve({});
        });
        const user = userEvent.setup();
        render(<App />);

        await screen.findByText('Tech');
        await user.type(screen.getByLabelText(/Name/), 'Design');
        await user.click(screen.getByRole('button', { name: 'Add Tag' }));

        expect(await screen.findByText('Design')).toBeInTheDocument();
    });

    it('shows a validation error when submitting the add-tag form without a name', async () => {
        mockTagsAndCounts();
        const user = userEvent.setup();
        render(<App />);

        await screen.findByText('Tech');
        const form = screen.getByRole('button', { name: 'Add Tag' }).closest('form');
        // The native "required" attribute would normally block submission —
        // bypass it via requestSubmit to exercise the app's own JS validation.
        form.noValidate = true;
        await user.click(screen.getByRole('button', { name: 'Add Tag' }));

        expect(await screen.findByText('Tag name is required.')).toBeInTheDocument();
    });
});
