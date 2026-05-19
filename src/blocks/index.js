import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import './editor.css';

const BLOCKS = [
	{
		name: 'field-title',
		title: 'Link: Title',
		description: 'Outputs the link title as an <h2> heading.',
		label: '[ Link Title ]',
		icon: 'heading',
	},
	{
		name: 'field-title-link',
		title: 'Link: Title as Link',
		description: 'Outputs the link title as a hyperlink to the URL. Ideal for roundup item templates.',
		label: '[ Title → URL ]',
		icon: 'admin-links',
	},
	{
		name: 'field-url',
		title: 'Link: URL',
		description: 'Outputs the raw URL.',
		label: '[ Link URL ]',
		icon: 'external',
	},
	{
		name: 'field-description',
		title: 'Link: Description',
		description: 'Outputs the saved description / notes.',
		label: '[ Description ]',
		icon: 'editor-paragraph',
	},
	{
		name: 'field-read-more',
		title: 'Link: Read More',
		description: 'Outputs a "Read more →" paragraph with the URL as href.',
		label: '[ Read More → ]',
		icon: 'arrow-right-alt',
	},
	{
		name: 'field-tags',
		title: 'Link: Tags',
		description: 'Outputs a comma-separated list of tags.',
		label: '[ Tags ]',
		icon: 'tag',
	},
];

BLOCKS.forEach( ( { name, title, description, label, icon } ) => {
	registerBlockType( `linkdigest/${ name }`, {
		apiVersion: 3,
		title,
		description,
		icon,
		category: 'text',
		edit: function PlaceholderEdit() {
			const blockProps = useBlockProps( {
				className: 'linkdigest-placeholder-block',
			} );
			return <div { ...blockProps }>{ label }</div>;
		},
		save: () => null,
	} );
} );
