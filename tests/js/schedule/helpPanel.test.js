import { openHelpTab } from '../../../src/schedule/lib/helpPanel';

function appendFixture({ hidden = true } = {}) {
    const wrap = document.createElement('div');
    wrap.id = 'contextual-help-wrap';
    if (hidden) wrap.classList.add('hidden');
    document.body.appendChild(wrap);

    const toggle = document.createElement('button');
    toggle.id = 'contextual-help-link';
    document.body.appendChild(toggle);

    const tabLi = document.createElement('li');
    tabLi.id = 'tab-link-lynxjournal-help-discord';
    const tabAnchor = document.createElement('a');
    tabLi.appendChild(tabAnchor);
    document.body.appendChild(tabLi);

    return { wrap, toggle, tabAnchor };
}

describe('openHelpTab', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('opens the Help dropdown when closed and clicks the matching tab link', () => {
        const { toggle, tabAnchor } = appendFixture({ hidden: true });
        const toggleClick = vi.fn();
        const tabClick = vi.fn();
        toggle.addEventListener('click', toggleClick);
        tabAnchor.addEventListener('click', tabClick);

        openHelpTab('discord');

        expect(toggleClick).toHaveBeenCalledTimes(1);
        expect(tabClick).toHaveBeenCalledTimes(1);
    });

    it('does not re-toggle the dropdown when it is already open', () => {
        const { toggle, tabAnchor } = appendFixture({ hidden: false });
        const toggleClick = vi.fn();
        const tabClick = vi.fn();
        toggle.addEventListener('click', toggleClick);
        tabAnchor.addEventListener('click', tabClick);

        openHelpTab('discord');

        expect(toggleClick).not.toHaveBeenCalled();
        expect(tabClick).toHaveBeenCalledTimes(1);
    });

    it('does nothing and does not throw when the Help dropdown is not on the page', () => {
        expect(() => openHelpTab('discord')).not.toThrow();
    });
});
