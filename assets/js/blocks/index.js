import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';

registerBlockType('ea-gaming-engine/arcade', {
	title: __('EA Gaming Arcade', 'ea-gaming-engine'),
	icon: 'games',
	category: 'widgets',
	attributes: {
		courseId: { type: 'number' },
		theme: { type: 'string', default: 'playful' },
		preset: { type: 'string', default: 'classic' }
	},
	edit: ({ attributes, setAttributes }) => {
		const { courseId, theme, preset } = attributes;
		return (
			<div {...useBlockProps()}>
				<p>{__('EA Gaming Arcade', 'ea-gaming-engine')}</p>
				<label>
					{__('Course ID', 'ea-gaming-engine')}
					<input type="number" value={courseId} onChange={(e) => setAttributes({ courseId: parseInt(e.target.value, 10) })} />
				</label>
				<label>
					{__('Theme', 'ea-gaming-engine')}
					<select value={theme} onChange={(e) => setAttributes({ theme: e.target.value })}>
						<option value="playful">{__('Playful', 'ea-gaming-engine')}</option>
						<option value="minimal_pro">{__('Minimal Pro', 'ea-gaming-engine')}</option>
					</select>
				</label>
				<label>
					{__('Preset', 'ea-gaming-engine')}
					<select value={preset} onChange={(e) => setAttributes({ preset: e.target.value })}>
						<option value="chill">{__('Chill', 'ea-gaming-engine')}</option>
						<option value="classic">{__('Classic', 'ea-gaming-engine')}</option>
						<option value="pro">{__('Pro', 'ea-gaming-engine')}</option>
					</select>
				</label>
			</div>
		);
	},
	save: ({ attributes }) => {
		const { courseId, theme, preset } = attributes;
		return (
			<div className="ea-gaming-launcher" data-course-id={courseId} data-theme={theme} data-preset={preset}>
				<button className="ea-gaming-start">{__('Start Game', 'ea-gaming-engine')}</button>
			</div>
		);
	}
});