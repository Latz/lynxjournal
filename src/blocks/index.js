import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, BlockControls } from '@wordpress/block-editor';
import { ToolbarGroup, DropdownMenu, ToolbarButton } from '@wordpress/components';
import './editor.css';

const BLOCK_SUPPORTS = {
	color: { text: true, background: true },
	typography: {
		fontSize: true,
		lineHeight: true,
		fontStyle: true,
		fontWeight: true,
		textDecoration: true,
		textTransform: true,
	},
	spacing: { padding: true, margin: true },
};

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
		description: 'Outputs the link title as a hyperlink to the URL. Ideal for digest item templates.',
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
	{
		name: 'field-current-date',
		title: 'Digest: Current Date',
		description: 'Outputs the current date when the digest is rendered.',
		label: '[ Current Date ]',
		icon: 'calendar-alt',
	},
];

BLOCKS.forEach( ( { name, title, description, label, icon } ) => {
	registerBlockType( `linkdigest/${ name }`, {
		apiVersion: 3,
		title,
		description,
		icon,
		category: 'text',
		supports: BLOCK_SUPPORTS,
		edit: function PlaceholderEdit() {
			const blockProps = useBlockProps( {
				className: 'linkdigest-placeholder-block',
			} );
			return <div { ...blockProps }>{ label }</div>;
		},
		save: () => null,
	} );
} );

registerBlockType( 'linkdigest/field-items-list', {
	apiVersion: 3,
	title: 'Items List',
	description: 'Marks where the digest items list is rendered. Choose ul or ol.',
	icon: 'list-view',
	category: 'text',
	attributes: {
		listType: {
			type: 'string',
			default: 'ul',
		},
	},
	edit: function ItemsListEdit( { attributes, setAttributes } ) {
		const { listType } = attributes;
		const blockProps = useBlockProps( { className: 'linkdigest-placeholder-block' } );
		return (
			<>
				<BlockControls>
					<ToolbarGroup>
						<ToolbarButton
							icon="editor-ul"
							label="Unordered list"
							isActive={ listType === 'ul' }
							onClick={ () => setAttributes( { listType: 'ul' } ) }
						/>
						<ToolbarButton
							icon="editor-ol"
							label="Ordered list"
							isActive={ listType === 'ol' }
							onClick={ () => setAttributes( { listType: 'ol' } ) }
						/>
					</ToolbarGroup>
				</BlockControls>
				<div { ...blockProps }>{ `[ Items (${ listType }) ]` }</div>
			</>
		);
	},
	save: () => null,
} );

registerBlockType( 'linkdigest/field-category', {
	apiVersion: 3,
	title: 'Category Heading',
	description: 'Outputs the category name as a heading. Use in the Digest Group template.',
	icon: 'heading',
	category: 'text',
	supports: BLOCK_SUPPORTS,
	attributes: {
		level: {
			type: 'number',
			default: 2,
		},
	},
	edit: function CategoryEdit( { attributes, setAttributes } ) {
		const { level } = attributes;
		const blockProps = useBlockProps( { className: 'linkdigest-placeholder-block' } );
		const levelControls = [ 1, 2, 3, 4, 5, 6 ].map( ( l ) => ( {
			title: `H${ l }`,
			isActive: level === l,
			onClick: () => setAttributes( { level: l } ),
		} ) );
		return (
			<>
				<BlockControls>
					<ToolbarGroup>
						<DropdownMenu
							icon="heading"
							label="Heading level"
							controls={ levelControls }
						/>
					</ToolbarGroup>
				</BlockControls>
				<div { ...blockProps }>{ `[ Category H${ level } ]` }</div>
			</>
		);
	},
	save: () => null,
} );
