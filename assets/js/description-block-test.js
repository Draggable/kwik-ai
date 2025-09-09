(function() {
    'use strict';

    const { __ } = wp.i18n;
    const { registerBlockType } = wp.blocks;
    const { useBlockProps } = wp.blockEditor;

    console.log('KWIK AI: Block script loading...');
    console.log('KWIK AI: wp.blocks available:', typeof wp.blocks);
    console.log('KWIK AI: registerBlockType available:', typeof registerBlockType);

    /**
     * Simple test block
     */
    registerBlockType('kwik-ai/description', {
        title: 'AI Description Test',
        description: 'Test block for AI descriptions',
        icon: 'text-page',
        category: 'common',
        edit: function() {
            const blockProps = useBlockProps();
            return wp.element.createElement('div', blockProps, 'AI Description Block - Test Mode');
        },
        save: function() {
            const blockProps = useBlockProps.save();
            return wp.element.createElement('div', blockProps, 'AI Description Block - Frontend');
        },
    });

    console.log('KWIK AI: Block registered successfully');

})();
