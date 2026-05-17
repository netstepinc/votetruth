const { addFilter } = wp.hooks;
const { createHigherOrderComponent } = wp.compose;

// Define Bootstrap options
const blockControlOptions = {
    'font-size': [
        { label: 'None', value: '' },
        { label: 'Font Size 1', value: 'fs-1' },
        { label: 'Font Size 2', value: 'fs-2' },
        { label: 'Font Size 3', value: 'fs-3' },
        { label: 'Font Size 4', value: 'fs-4' },
        { label: 'Font Size 5', value: 'fs-5' },
        { label: 'Font Size 6', value: 'fs-6' },
    ],
    'font-weight': [
        { label: 'None', value: ''},
        { label: 'Bold (700)', value: 'fw-bold'},
        { label: 'Bolder (900)', value: 'fw-bolder'},
        { label: 'Semibold (600)', value: 'fw-semibold'},
        { label: 'Medium (500)', value: 'fw-medium'},
        { label: 'Normal (400)', value: 'fw-normal'},
        { label: 'Light (300)', value: 'fw-light'},
        { label: 'Lighter (200)', value: 'fw-lighter'},
        /*
        { label: 'Italic', value: 'fst-italic'},
        { label: 'Normal', value: 'fst-normal'},
        */
    ],
    'text-color': [
        { label: 'None', value: ''},
        { label: 'Text Light', value: 'text-light'},
        { label: 'Text Dark', value: 'text-dark'},
        { label: 'Text Primary', value: 'text-primary'},
        { label: 'Text Secondary', value: 'text-secondary'},
        { label: 'Text Success', value: 'text-success'},
        { label: 'Text Danger', value: 'text-danger'},
        { label: 'Text Warning', value: 'text-warning'},
        { label: 'Text Info', value: 'text-info'},
    ],
    /*
    'link-color': [
        { label: 'None', value: ''},
        { label: 'Link Light', value: 'link-light'},
        { label: 'Link Dark', value: 'link-dark'},
        { label: 'Link Primary', value: 'link-primary'},
        { label: 'Link Secondary', value: 'link-secondary'},
        { label: 'Link Success', value: 'link-success'},
        { label: 'Link Danger', value: 'link-danger'},
        { label: 'Link Warning', value: 'link-warning'},
        { label: 'Link Info', value: 'link-info'},
        { label: 'Link Emphasis', value: 'link-body-emphasis'},
    ],
    */
    'button': [
        { label: 'None', value: ''},
        { label: 'Button', value: 'btn'},
    ],
    'button-color': [
        { label: 'None', value: ''},
        { label: 'Button Primary', value: 'btn-primary'},
        { label: 'Button Secondary', value: 'btn-secondary'},
        { label: 'Button Success', value: 'btn-success'},
        { label: 'Button Danger', value: 'btn-danger'},
        { label: 'Button Outline', value: 'btn-outline-primary'},
        { label: 'Button Outline Secondary', value: 'btn-outline-secondary'},
        { label: 'Button Outline Success', value: 'btn-outline-success'},
        { label: 'Button Outline Danger', value: 'btn-outline-danger'},
    ],
    'button-size': [
        { label: 'None', value: ''},
        { label: 'Button Small', value: 'btn-sm'},
        { label: 'Button Large', value: 'btn-lg'},
    ],
    'container': [
        { label: 'None', value: '' },
        { label: 'Container', value: 'container' },
        { label: 'Container Large', value: 'container-xl' },
        { label: 'Container Extra Large', value: 'container-xxl' },
        { label: 'Container Fluid', value: 'container-fluid' },
        { label: 'Row', value: 'row' }
    ],
    'gutter': [
        { label: 'None', value: '' },
        { label: 'Gutter 1', value: 'g-1' },
        { label: 'Gutter 2', value: 'g-2' },
        { label: 'Gutter 3', value: 'g-3' },
        { label: 'Gutter 4', value: 'g-4' },
        { label: 'Gutter 5', value: 'g-5' },
    ],
    'columns': [
        { label: 'None', value: '' },
        { label: 'Col-1/12', value: 'col-1' },
        { label: 'Col-2/12', value: 'col-2' },
        { label: 'Col-3/12', value: 'col-3' },
        { label: 'Col-4/12', value: 'col-4' },
        { label: 'Col-5/12', value: 'col-5' },
        { label: 'Col-6/12', value: 'col-6' },
        { label: 'Col-7/12', value: 'col-7' },
        { label: 'Col-8/12', value: 'col-8' },
        { label: 'Col-9/12', value: 'col-9' },
        { label: 'Col-10/12', value: 'col-10' },
        { label: 'Col-11/12', value: 'col-11' },
        { label: 'Col-12/12', value: 'col-12' },
    ],
    'columns-sm': [
        { label: 'None', value: '' },
        { label: 'Col-1/12', value: 'col-sm-1' },
        { label: 'Col-2/12', value: 'col-sm-2' },
        { label: 'Col-3/12', value: 'col-sm-3' },
        { label: 'Col-4/12', value: 'col-sm-4' },
        { label: 'Col-5/12', value: 'col-sm-5' },
        { label: 'Col-6/12', value: 'col-sm-6' },
        { label: 'Col-7/12', value: 'col-sm-7' },
        { label: 'Col-8/12', value: 'col-sm-8' },
        { label: 'Col-9/12', value: 'col-sm-9' },
        { label: 'Col-10/12', value: 'col-sm-10' },
        { label: 'Col-11/12', value: 'col-sm-11' },
        { label: 'Col-12/12', value: 'col-sm-12' },
    ],
    'columns-md': [
        { label: 'None', value: '' },
        { label: 'Col-md-1/12', value: 'col-md-1' },
        { label: 'Col-md-2/12', value: 'col-md-2' },
        { label: 'Col-md-3/12', value: 'col-md-3' },
        { label: 'Col-md-4/12', value: 'col-md-4' },
        { label: 'Col-md-5/12', value: 'col-md-5' },
        { label: 'Col-md-6/12', value: 'col-md-6' },
        { label: 'Col-md-7/12', value: 'col-md-7' },
        { label: 'Col-md-8/12', value: 'col-md-8' },
        { label: 'Col-md-9/12', value: 'col-md-9' },
        { label: 'Col-md-10/12', value: 'col-md-10' },
        { label: 'Col-md-11/12', value: 'col-md-11' },
        { label: 'Col-md-12/12', value: 'col-md-12' },
    ],
    'columns-lg': [
        { label: 'None', value: '' },
        { label: 'Col-lg-1/12', value: 'col-lg-1' },
        { label: 'Col-lg-2/12', value: 'col-lg-2' },
        { label: 'Col-lg-3/12', value: 'col-lg-3' },
        { label: 'Col-lg-4/12', value: 'col-lg-4' },
        { label: 'Col-lg-5/12', value: 'col-lg-5' },
        { label: 'Col-lg-6/12', value: 'col-lg-6' },
        { label: 'Col-lg-7/12', value: 'col-lg-7' },
        { label: 'Col-lg-8/12', value: 'col-lg-8' },
        { label: 'Col-lg-9/12', value: 'col-lg-9' },
        { label: 'Col-lg-10/12', value: 'col-lg-10' },
        { label: 'Col-lg-11/12', value: 'col-lg-11' },
        { label: 'Col-lg-12/12', value: 'col-lg-12' },
    ],
    'offset': [
        { label: 'None', value: '' },
        { label: 'Offset 1', value: 'offset-1' },
        { label: 'Offset 2', value: 'offset-2' },
        { label: 'Offset 3', value: 'offset-3' },
        { label: 'Offset 4', value: 'offset-4' },
        { label: 'Offset 5', value: 'offset-5' },
        { label: 'Offset 6', value: 'offset-6' },
        { label: 'Offset 7', value: 'offset-7' },
        { label: 'Offset 8', value: 'offset-8' },
        { label: 'Offset 9', value: 'offset-9' },
        { label: 'Offset 10', value: 'offset-10' },
        { label: 'Offset 11', value: 'offset-11' },
    ],
    'offset-md': [
        { label: 'None', value: '' },
        { label: 'Offset-md 1', value: 'offset-md-1' },
        { label: 'Offset-md 2', value: 'offset-md-2' },
        { label: 'Offset-md 3', value: 'offset-md-3' },
        { label: 'Offset-md 4', value: 'offset-md-4' },
        { label: 'Offset-md 5', value: 'offset-md-5' },
        { label: 'Offset-md 6', value: 'offset-md-6' },
        { label: 'Offset-md 7', value: 'offset-md-7' },
        { label: 'Offset-md 8', value: 'offset-md-8' },
        { label: 'Offset-md 9', value: 'offset-md-9' },
        { label: 'Offset-md 10', value: 'offset-md-10' },
        { label: 'Offset-md 11', value: 'offset-md-11' },
    ],
    'offset-lg': [
        { label: 'None', value: '' },
        { label: 'Offset-lg 1', value: 'offset-lg-1' },
        { label: 'Offset-lg 2', value: 'offset-lg-2' },
        { label: 'Offset-lg 3', value: 'offset-lg-3' },
        { label: 'Offset-lg 4', value: 'offset-lg-4' },
        { label: 'Offset-lg 5', value: 'offset-lg-5' },
        { label: 'Offset-lg 6', value: 'offset-lg-6' },
        { label: 'Offset-lg 7', value: 'offset-lg-7' },
        { label: 'Offset-lg 8', value: 'offset-lg-8' },
        { label: 'Offset-lg 9', value: 'offset-lg-9' },
        { label: 'Offset-lg 10', value: 'offset-lg-10' },
        { label: 'Offset-lg 11', value: 'offset-lg-11' },
    ],
    'margin': [
        { label: 'None', value: '' },
        { label: 'Margin 1', value: 'm-1' },
        { label: 'Margin 2', value: 'm-2' },
        { label: 'Margin 3', value: 'm-3' },
        { label: 'Margin 4', value: 'm-4' },
        { label: 'Margin 5', value: 'm-5' },
    ],
    'margin-top': [
        { label: 'None', value: '' },
        { label: 'Top Margin 1', value: 'mt-1' },
        { label: 'Top Margin 2', value: 'mt-2' },
        { label: 'Top Margin 3', value: 'mt-3' },
        { label: 'Top Margin 4', value: 'mt-4' },
        { label: 'Top Margin 5', value: 'mt-5' },
    ],
    'margin-bottom': [
        { label: 'None', value: '' },
        { label: 'Bottom Margin 1', value: 'mb-1' },
        { label: 'Bottom Margin 2', value: 'mb-2' },
        { label: 'Bottom Margin 3', value: 'mb-3' },
        { label: 'Bottom Margin 4', value: 'mb-4' },
        { label: 'Bottom Margin 5', value: 'mb-5' },
    ],
    'margin-x': [
        { label: 'None', value: '' },
        { label: 'Horizontal Margin 1', value: 'mx-1' },
        { label: 'Horizontal Margin 2', value: 'mx-2' },
        { label: 'Horizontal Margin 3', value: 'mx-3' },
        { label: 'Horizontal Margin 4', value: 'mx-4' },
        { label: 'Horizontal Margin 5', value: 'mx-5' },
        { label: 'Horizontal Margin Auto', value: 'mx-auto' },
    ],
    'margin-y': [
        { label: 'None', value: '' },
        { label: 'Vertical Margin 1', value: 'my-1' },
        { label: 'Vertical Margin 2', value: 'my-2' },
        { label: 'Vertical Margin 3', value: 'my-3' },
        { label: 'Vertical Margin 4', value: 'my-4' },
        { label: 'Vertical Margin 5', value: 'my-5' },
        { label: 'Vertical Margin Auto', value: 'my-auto' },
    ],
    'padding': [
        { label: 'None', value: '' }, 
        { label: 'Padding 1', value: 'p-1' },
        { label: 'Padding 2', value: 'p-2' },
        { label: 'Padding 3', value: 'p-3' },
        { label: 'Padding 4', value: 'p-4' },
        { label: 'Padding 5', value: 'p-5' }
    ],
    'padding-top': [
        { label: 'None', value: '' },
        { label: 'Top Padding 1', value: 'pt-1' },
        { label: 'Top Padding 2', value: 'pt-2' },
        { label: 'Top Padding 3', value: 'pt-3' },
        { label: 'Top Padding 4', value: 'pt-4' },
        { label: 'Top Padding 5', value: 'pt-5' },
    ],
    'padding-bottom': [
        { label: 'None', value: '' },
        { label: 'Bottom Padding 1', value: 'pb-1' },
        { label: 'Bottom Padding 2', value: 'pb-2' },
        { label: 'Bottom Padding 3', value: 'pb-3' },
        { label: 'Bottom Padding 4', value: 'pb-4' },
        { label: 'Bottom Padding 5', value: 'pb-5' },
    ],
    'padding-x': [
        { label: 'None', value: '' },
        { label: 'Horizontal Padding 1', value: 'px-1' },
        { label: 'Horizontal Padding 2', value: 'px-2' },
        { label: 'Horizontal Padding 3', value: 'px-3' },
        { label: 'Horizontal Padding 4', value: 'px-4' },
        { label: 'Horizontal Padding 5', value: 'px-5' },
    ],
    'padding-y': [
        { label: 'None', value: '' },
        { label: 'Vertical Padding 1', value: 'py-1' },
        { label: 'Vertical Padding 2', value: 'py-2' },
        { label: 'Vertical Padding 3', value: 'py-3' },
        { label: 'Vertical Padding 4', value: 'py-4' },
        { label: 'Vertical Padding 5', value: 'py-5' },
    ],
    'border': [
        { label: 'None', value: '' },
        { label: 'Border', value: 'border' },
        { label: 'Border Top', value: 'border-top' },
        { label: 'Border Bottom', value: 'border-bottom' },
        { label: 'Border Left', value: 'border-left' },
        { label: 'Border Right', value: 'border-right' },
    ],
    'background-color': [
        { label: 'None', value: '' },
        { label: 'Light', value: 'bg-light' },
        { label: 'Dark', value: 'bg-dark' },
        { label: 'Primary', value: 'bg-primary' },
        { label: 'Secondary', value: 'bg-secondary' },
        { label: 'Info', value: 'bg-info' },
        { label: 'Success', value: 'bg-success' },
        { label: 'Danger', value: 'bg-danger' },
        { label: 'Warning', value: 'bg-warning' },
    ],
    'display': [
        { label: 'None', value: '' },
        { label: 'Hide on Mobile+', value: 'd-none' },
        { label: 'Display Mobile+', value: 'd-block' },
        { label: 'Display Inline', value: 'd-inline' },
        { label: 'Display Inline Block', value: 'd-inline-block' },
        { label: 'Display Flex', value: 'd-flex' },
        { label: 'Display Inline Flex', value: 'd-inline-flex' },
        { label: 'Display Grid', value: 'd-grid' },
        { label: 'Display Inline Grid', value: 'd-inline-grid' },
        { label: 'Display None', value: 'd-none' },
    ],
    'display-sm': [
        { label: 'None', value: '' },
        { label: 'Hide on Small+', value: 'd-sm-none' },
        { label: 'Display Small', value: 'd-sm-block' },
        { label: 'Display Inline', value: 'd-sm-inline' },
        { label: 'Display Inline Block', value: 'd-sm-inline-block' },
        { label: 'Display Flex', value: 'd-sm-flex' },
        { label: 'Display Inline Flex', value: 'd-sm-inline-flex' },
        { label: 'Display Grid', value: 'd-sm-grid' },
        { label: 'Display Inline Grid', value: 'd-sm-inline-grid' },
        { label: 'Display None', value: 'd-sm-none' },
    ],
    'display-md': [
        { label: 'None', value: '' },
        { label: 'Hide on Medium+', value: 'd-md-none' },
        { label: 'Display Medium', value: 'd-md-block' },
        { label: 'Display Inline', value: 'd-md-inline' },
        { label: 'Display Inline Block', value: 'd-md-inline-block' },
        { label: 'Display Flex', value: 'd-md-flex' },
        { label: 'Display Inline Flex', value: 'd-md-inline-flex' },
        { label: 'Display Grid', value: 'd-md-grid' },
        { label: 'Display Inline Grid', value: 'd-md-inline-grid' },
        { label: 'Display None', value: 'd-md-none' },
    ],
    'display-lg': [
        { label: 'None', value: '' },
        { label: 'Hide on Large+', value: 'd-lg-none' },
        { label: 'Display Large', value: 'd-lg-block' },
        { label: 'Display Inline', value: 'd-lg-inline' },
        { label: 'Display Inline Block', value: 'd-lg-inline-block' },
        { label: 'Display Flex', value: 'd-lg-flex' },
        { label: 'Display Inline Flex', value: 'd-lg-inline-flex' },
        { label: 'Display Grid', value: 'd-lg-grid' },
        { label: 'Display Inline Grid', value: 'd-lg-inline-grid' },
        { label: 'Display None', value: 'd-lg-none' },
    ],
    'display-xl': [
        { label: 'None', value: '' },
        { label: 'Hide on XL Large+', value: 'd-xl-none' },
        { label: 'Display XL Large', value: 'd-xl-block' },
        { label: 'Display Inline', value: 'd-xl-inline' },
        { label: 'Display Inline Block', value: 'd-xl-inline-block' },
        { label: 'Display Flex', value: 'd-xl-flex' },
        { label: 'Display Inline Flex', value: 'd-xl-inline-flex' },
        { label: 'Display Grid', value: 'd-xl-grid' },
        { label: 'Display Inline Grid', value: 'd-xl-inline-grid' },
        { label: 'Display None', value: 'd-xl-none' },
    ],
/*    
	'display-xxl': [
        { label: 'None', value: '' },
        { label: 'Hide on XXL Large+', value: 'd-xxl-none' },
        { label: 'Display XXL Large', value: 'd-xxl-block' },
        { label: 'Display Inline', value: 'd-xxl-inline' },
        { label: 'Display Inline Block', value: 'd-xxl-inline-block' },
        { label: 'Display Flex', value: 'd-xxl-flex' },
        { label: 'Display Inline Flex', value: 'd-xxl-inline-flex' },
        { label: 'Display Grid', value: 'd-xxl-grid' },
        { label: 'Display Inline Grid', value: 'd-xxl-inline-grid' },
        { label: 'Display None', value: 'd-xxl-none' },
    ],
*/
    'fade': [
        { label: 'None', value: '' },
        { label: 'Fade-in', value: 'fade-in' },
        { label: 'Fade-in-left', value: 'fade-in-left' },
        { label: 'Fade-in-right', value: 'fade-in-right' },
        { label: 'Fade-in-up', value: 'fade-in-up' },
        { label: 'Fade-in-down', value: 'fade-in-down' },
        /*
        { label: 'Fade-out', value: 'fade-out' },
        { label: 'Fade-out-down', value: 'fade-out-down' },
        { label: 'Fade-out-left', value: 'fade-out-left' },
        { label: 'Fade-out-right', value: 'fade-out-right' },
        { label: 'Fade-out-up', value: 'fade-out-up' },
        */
    ],
    'slide': [
        { label: 'None', value: '' },
        { label: 'Slide-in-left', value: 'slide-in-left' },
        { label: 'Slide-in-right', value: 'slide-in-right' },
        { label: 'Slide-in-up', value: 'slide-in-up' },
        { label: 'Slide-in-down', value: 'slide-in-down' },
        /*
        { label: 'Slide-out-down', value: 'slide-out-down' },
        { label: 'Slide-out-left', value: 'slide-out-left' },
        { label: 'Slide-out-right', value: 'slide-out-right' },
        { label: 'Slide-out-up', value: 'slide-out-up' },
        { label: 'Slide-down', value: 'slide-down' },
        { label: 'Slide-left', value: 'slide-left' },
        { label: 'Slide-right', value: 'slide-right' },
        { label: 'Slide-up', value: 'slide-up' },
         */
    ],
    'zoom': [
        { label: 'None', value: '' },
        { label: 'Zoom-in', value: 'zoom-in' },
        { label: 'Zoom-out', value: 'zoom-out' },
    ],
    /*
    'tada': [
        { label: 'None', value: '' },
        { label: 'Tada', value: 'tada' },
        { label: 'Pulse', value: 'pulse' }
    ]
    */
};

// Helper function to find existing class by prefix
function getBlockControlClass(classes, prefix) {
    if (!classes) return '';
    const classArray = classes.split(' ');
    if (Array.isArray(prefix)) {
        return classArray.find((cls) => prefix.some(p => cls === p)) || '';
    }
    return classArray.find((cls) => cls.startsWith(prefix)) || '';
}

// Helper function to update classes
function updateClasses(className, value, type, options = blockControlOptions) {
    // Get className from the current block's attributes
    const currentClasses = (className || '').split(' ').filter(Boolean);
    
    // Handle Bootstrap classes that use prefixes
    const filteredClasses = currentClasses.filter((cls) => 
        !options[type].some(opt => opt.value === cls)
    );
    
    // Add the new value if it exists
    if (value) {
        filteredClasses.push(value);
    }

    // Return the new className string
    return filteredClasses.join(' ').trim();
}

// Add custom Bootstrap controls to Advanced panel
const withBootstrapControls = createHigherOrderComponent(function(BlockEdit) {
    return function(props) {
        const attributes = props.attributes;
        const setAttributes = props.setAttributes;
        const className = attributes.className || '';

        // Create panel body element
        const panelBody = wp.element.createElement(
            wp.components.PanelBody,
            { title: 'Bootstrap Classes', initialOpen: false },
            wp.element.createElement(
                'h3',
                null,
                'Typography'
            ),
            wp.element.createElement(wp.components.SelectControl, {
                label: 'Font Size',
                value: getBlockControlClass(className, 'fs-'),
                options: blockControlOptions['font-size'],
                onChange: function(value) { setAttributes({ className: updateClasses(className, value, 'font-size') }); },
                className: getBlockControlClass(className, 'fs-') ? 'has-value' : ''
            }),
            wp.element.createElement(wp.components.SelectControl, {
                label: 'Font Weight',
                value: getBlockControlClass(className, 'fw-'),
                options: blockControlOptions['font-weight'],
                onChange: function(value) { setAttributes({ className: updateClasses(className, value, 'font-weight') }); },
                className: getBlockControlClass(className, 'fw-') ? 'has-value' : ''
            }),
            wp.element.createElement(wp.components.SelectControl, {
                label: 'Text Color',
                value: getBlockControlClass(className, 'text-'),
                options: blockControlOptions['text-color'],
                onChange: function(value) { setAttributes({ className: updateClasses(className, value, 'text-color') }); },
                className: getBlockControlClass(className, 'text-') ? 'has-value' : ''
            }),
            /*
            wp.element.createElement(wp.components.SelectControl, {
                label: 'Link Color',
                value: getBlockControlClass(className, 'link-'),
                options: blockControlOptions['link-color'],
                onChange: function(value) { setAttributes({ className: updateClasses(className, value, 'link-color') }); },
                className: getBlockControlClass(className, 'link-') ? 'has-value' : ''
            }),
            */
            wp.element.createElement(
                'h3',
                null,
                'Appearance'
            ),
            wp.element.createElement(wp.components.SelectControl, {
                label: 'Border',
                value: getBlockControlClass(className, 'border'),
                options: blockControlOptions['border'],
                onChange: function(value) { setAttributes({ className: updateClasses(className, value, 'border') }); },
                className: getBlockControlClass(className, 'border') ? 'has-value' : ''
            }),
            wp.element.createElement(wp.components.SelectControl, {
                label: 'Background Color',
                value: getBlockControlClass(className, 'bg-'),
                options: blockControlOptions['background-color'],
                onChange: function(value) { setAttributes({ className: updateClasses(className, value, 'background-color') }); },
                className: getBlockControlClass(className, 'bg-') ? 'has-value' : ''
            }),
            wp.element.createElement(
                'h3',
                null,
                'Button'
            ),
            wp.element.createElement(wp.components.SelectControl, {
                label: 'Button',
                value: getBlockControlClass(className, ['btn']),
                options: blockControlOptions['button'],
                onChange: function(value) { setAttributes({ className: updateClasses(className, value, 'button') }); },
                className: getBlockControlClass(className, 'btn') ? 'has-value' : ''
            }),
            wp.element.createElement(wp.components.SelectControl, {
                label: 'Button Color',
                value: getBlockControlClass(className, ['btn-primary', 'btn-secondary', 'btn-success', 'btn-danger', 'btn-outline-primary', 'btn-outline-secondary', 'btn-outline-success', 'btn-outline-danger']),
                options: blockControlOptions['button-color'],
                onChange: function(value) { setAttributes({ className: updateClasses(className, value, 'button-color') }); },
                className: getBlockControlClass(className, ['btn-primary', 'btn-secondary', 'btn-success', 'btn-danger', 'btn-outline-primary', 'btn-outline-secondary', 'btn-outline-success', 'btn-outline-danger']) ? 'has-value' : ''
            }),
            wp.element.createElement(wp.components.SelectControl, {
                label: 'Button Size',
                value: getBlockControlClass(className, ['btn-sm', 'btn-lg']),
                options: blockControlOptions['button-size'],
                onChange: function(value) { setAttributes({ className: updateClasses(className, value, 'button-size') }); },
                className: getBlockControlClass(className, ['btn-sm', 'btn-lg']) ? 'has-value' : ''
            }),
            wp.element.createElement(
                'h3',
                null,
                'Margin'
            ),
            wp.element.createElement(wp.components.SelectControl, {
                label: 'Margin (All Sides)',
                value: getBlockControlClass(className, 'm-'),
                options: blockControlOptions.margin,
                onChange: function(value) { setAttributes({ className: updateClasses(className, value, 'margin') }); },
                className: getBlockControlClass(className, 'm-') ? 'has-value' : ''
            }),
            wp.element.createElement(wp.components.SelectControl, {
                label: 'Top Margin',
                value: getBlockControlClass(className, 'mt-'),
                options: blockControlOptions['margin-top'],
                onChange: function(value) { setAttributes({ className: updateClasses(className, value, 'margin-top') }); },
                className: getBlockControlClass(className, 'mt-') ? 'has-value' : ''
            }),
            wp.element.createElement(wp.components.SelectControl, {
                label: 'Bottom Margin',
                value: getBlockControlClass(className, 'mb-'),
                options: blockControlOptions['margin-bottom'],
                onChange: function(value) { setAttributes({ className: updateClasses(className, value, 'margin-bottom') }); },
                className: getBlockControlClass(className, 'mb-') ? 'has-value' : ''
            }),
            wp.element.createElement(wp.components.SelectControl, {
                label: 'Horizontal Margin',
                value: getBlockControlClass(className, 'mx-'),
                options: blockControlOptions['margin-x'],
                onChange: function(value) { setAttributes({ className: updateClasses(className, value, 'margin-x') }); },
                className: getBlockControlClass(className, 'mx-') ? 'has-value' : ''
            }),
            wp.element.createElement(wp.components.SelectControl, {
                label: 'Vertical Margin',
                value: getBlockControlClass(className, 'my-'),
                options: blockControlOptions['margin-y'],
                onChange: function(value) { setAttributes({ className: updateClasses(className, value, 'margin-y') }); },
                className: getBlockControlClass(className, 'my-') ? 'has-value' : ''
            }),
            wp.element.createElement(
                'h3',
                null,
                'Padding'
            ),
            wp.element.createElement(wp.components.SelectControl, {
                label: 'Padding (All Sides)',
                value: getBlockControlClass(className, 'p-'),
                options: blockControlOptions.padding,
                onChange: function(value) { setAttributes({ className: updateClasses(className, value, 'padding') }); },
                className: getBlockControlClass(className, 'p-') ? 'has-value' : ''
            }),
            wp.element.createElement(wp.components.SelectControl, {
                label: 'Top Padding',
                value: getBlockControlClass(className, 'pt-'),
                options: blockControlOptions['padding-top'],
                onChange: function(value) { setAttributes({ className: updateClasses(className, value, 'padding-top') }); },
                className: getBlockControlClass(className, 'pt-') ? 'has-value' : ''
            }),
            wp.element.createElement(wp.components.SelectControl, {
                label: 'Bottom Padding',
                value: getBlockControlClass(className, 'pb-'),
                options: blockControlOptions['padding-bottom'],
                onChange: function(value) { setAttributes({ className: updateClasses(className, value, 'padding-bottom') }); },
                className: getBlockControlClass(className, 'pb-') ? 'has-value' : ''
            }),
            wp.element.createElement(wp.components.SelectControl, {
                label: 'Horizontal Padding',
                value: getBlockControlClass(className, 'px-'),
                options: blockControlOptions['padding-x'],
                onChange: function(value) { setAttributes({ className: updateClasses(className, value, 'padding-x') }); },
                className: getBlockControlClass(className, 'px-') ? 'has-value' : ''
            }),
            wp.element.createElement(wp.components.SelectControl, {
                label: 'Vertical Padding',
                value: getBlockControlClass(className, 'py-'),
                options: blockControlOptions['padding-y'],
                onChange: function(value) { setAttributes({ className: updateClasses(className, value, 'padding-y') }); },
                className: getBlockControlClass(className, 'py-') ? 'has-value' : ''
            }),
            wp.element.createElement(
                'h3',
                null,
                'Layout'
            ),
            wp.element.createElement(wp.components.SelectControl, {
                label: 'Container/Row',
                value: getBlockControlClass(className, ['container','container-fluid','container-xl','container-xxl','row']),
                options: blockControlOptions['container'],
                onChange: function(value) { setAttributes({ className: updateClasses(className, value, 'container') }); },
                className: getBlockControlClass(className, ['container','container-fluid','container-xl','container-xxl','row']) ? 'has-value' : ''
            }),
            wp.element.createElement(wp.components.SelectControl, {
                label: 'Column Gutter',
                value: getBlockControlClass(className, 'g-'),
                options: blockControlOptions['gutter'],
                onChange: function(value) { setAttributes({ className: updateClasses(className, value, 'gutter') }); },
                className: getBlockControlClass(className, 'g-') ? 'has-value' : ''
            }),
            wp.element.createElement(wp.components.SelectControl, {
                label: 'Columns (All Sizes)',
                value: getBlockControlClass(className,['col-1','col-2','col-3','col-4','col-5','col-6','col-7','col-8','col-9','col-10','col-11','col-12']),
                options: blockControlOptions['columns'],
                onChange: function(value) { setAttributes({ className: updateClasses(className, value, 'columns') }); },
                className: getBlockControlClass(className, ['col-1','col-2','col-3','col-4','col-5','col-6','col-7','col-8','col-9','col-10','col-11','col-12']) ? 'has-value' : ''
            }),
            wp.element.createElement(wp.components.SelectControl, {
                label: 'Columns Small >=576px',
                value: getBlockControlClass(className, 'col-sm'),
                options: blockControlOptions['columns-sm'],
                onChange: function(value) { setAttributes({ className: updateClasses(className, value, 'columns-sm') }); },
                className: getBlockControlClass(className, 'col-sm') ? 'has-value' : ''
            }),
            wp.element.createElement(wp.components.SelectControl, {
                label: 'Columns Medium >=768px',
                value: getBlockControlClass(className, 'col-md'),
                options: blockControlOptions['columns-md'],
                onChange: function(value) { setAttributes({ className: updateClasses(className, value, 'columns-md') }); },
                className: getBlockControlClass(className, 'col-md') ? 'has-value' : ''
            }),
            wp.element.createElement(wp.components.SelectControl, {
                label: 'Columns Large >=992px',
                value: getBlockControlClass(className, 'col-lg'),
                options: blockControlOptions['columns-lg'],
                onChange: function(value) { setAttributes({ className: updateClasses(className, value, 'columns-lg') }); },
                className: getBlockControlClass(className, 'col-lg') ? 'has-value' : ''
            }),
            //offset
            wp.element.createElement(wp.components.SelectControl, {
                label: 'Offset',
                value: getBlockControlClass(className, ['offset-1','offset-2','offset-3','offset-4','offset-5','offset-6','offset-7','offset-8','offset-9','offset-10','offset-11']),
                options: blockControlOptions['offset'],
                onChange: function(value) { setAttributes({ className: updateClasses(className, value, 'offset') }); },
                className: getBlockControlClass(className, ['offset-1','offset-2','offset-3','offset-4','offset-5','offset-6','offset-7','offset-8','offset-9','offset-10','offset-11']) ? 'has-value' : ''
            }),
            wp.element.createElement(wp.components.SelectControl, {
                label: 'Offset Medium >=768px',
                value: getBlockControlClass(className, 'offset-md'),
                options: blockControlOptions['offset-md'],
                onChange: function(value) { setAttributes({ className: updateClasses(className, value, 'offset-md') }); },
                className: getBlockControlClass(className, 'offset-md') ? 'has-value' : ''
            }),
            wp.element.createElement(wp.components.SelectControl, {
                label: 'Offset Large >=992px',
                value: getBlockControlClass(className, 'offset-lg'),
                options: blockControlOptions['offset-lg'],
                onChange: function(value) { setAttributes({ className: updateClasses(className, value, 'offset-lg') }); },
                className: getBlockControlClass(className, 'offset-lg') ? 'has-value' : ''
            }),
            wp.element.createElement(
                'h3',
                null,
                'Responsive Display'
            ),
            wp.element.createElement(wp.components.SelectControl, {
                label: 'Display',
                value: getBlockControlClass(className, ['d-none', 'd-block', 'd-inline', 'd-inline-block', 'd-flex', 'd-inline-flex', 'd-grid', 'd-inline-grid', 'd-none']),
                options: blockControlOptions.display,
                onChange: function(value) { setAttributes({ className: updateClasses(className, value, 'display') }); },
                className: getBlockControlClass(className, ['d-none', 'd-block', 'd-inline', 'd-inline-block', 'd-flex', 'd-inline-flex', 'd-grid', 'd-inline-grid', 'd-none']) ? 'has-value' : ''
            }),
            wp.element.createElement(wp.components.SelectControl, {
                label: 'Display Small >=576px',
                value: getBlockControlClass(className, 'd-sm-'),
                options: blockControlOptions['display-sm'],
                onChange: function(value) { setAttributes({ className: updateClasses(className, value, 'display-sm') }); },
                className: getBlockControlClass(className, 'd-sm-') ? 'has-value' : ''
            }),
            wp.element.createElement(wp.components.SelectControl, {
                label: 'Display Medium >=768px',
                value: getBlockControlClass(className, 'd-md-'),
                options: blockControlOptions['display-md'],
                onChange: function(value) { setAttributes({ className: updateClasses(className, value, 'display-md') }); },
                className: getBlockControlClass(className, 'd-md-') ? 'has-value' : ''
            }),
            wp.element.createElement(wp.components.SelectControl, {
                label: 'Display Large >=992px',
                value: getBlockControlClass(className, 'd-lg-'),
                options: blockControlOptions['display-lg'],
                onChange: function(value) { setAttributes({ className: updateClasses(className, value, 'display-lg') }); },
                className: getBlockControlClass(className, 'd-lg-') ? 'has-value' : ''
            }),
            wp.element.createElement(wp.components.SelectControl, {
                label: 'Display Extra Large >=1200px',
                value: getBlockControlClass(className, 'd-xl-'),
                options: blockControlOptions['display-xl'],
                onChange: function(value) { setAttributes({ className: updateClasses(className, value, 'display-xl') }); },
                className: getBlockControlClass(className, 'd-xl-') ? 'has-value' : ''
            }),
            wp.element.createElement(wp.components.SelectControl, {
                label: 'Display Extra Extra Large >=1400px',
                value: getBlockControlClass(className, 'd-xxl-'),
                options: blockControlOptions['display-xxl'],
                onChange: function(value) { setAttributes({ className: updateClasses(className, value, 'display-xxl') }); },
                className: getBlockControlClass(className, 'd-xxl-') ? 'has-value' : ''
            }),
        );

        // Create inspector controls
        const inspectorControls = wp.element.createElement(
            wp.blockEditor.InspectorControls,
            null,
            panelBody
        );

        // Return fragment with both elements
        return wp.element.createElement(
            wp.element.Fragment,
            null,
            wp.element.createElement(BlockEdit, props),
            inspectorControls
        );
    };
}, 'withBootstrapControls');

// Apply the custom controls to all blocks
addFilter('editor.BlockEdit','jbs/with-bootstrap-controls',withBootstrapControls);

// Define animation options
const blockAnimationOptions = {
    'animation-type': [
        { label: 'None', value: '' },
        { label: 'Fade In', value: 'gsap-fade-in' },
        { label: 'Slide from Left', value: 'gsap-slide-left' },
        { label: 'Slide from Right', value: 'gsap-slide-right' },
		{ label: 'Slide from Top', value: 'gsap-slide-top' },
		{ label: 'Slide from Bottom', value: 'gsap-slide-bottom' },
        { label: 'Zoom In', value: 'gsap-zoom-in' }
    ],
    'animation-duration': [
        { label: 'None', value: '' },
        { label: 'Default (1s)', value: 'gsap-duration-1' },
        { label: '0.5s', value: 'gsap-duration-0.5' },
        { label: '1.5s', value: 'gsap-duration-1.5' },
        { label: '2s', value: 'gsap-duration-2' }
    ],
    'animation-delay': [
        { label: 'None', value: '' },
        { label: 'No Delay', value: 'gsap-delay-0' },
        { label: '0.5s', value: 'gsap-delay-0.5' },
        { label: '1s', value: 'gsap-delay-1' },
        { label: '1.5s', value: 'gsap-delay-1.5' },
		{ label: '2s', value: 'gsap-delay-2' },
		{ label: '2.5s', value: 'gsap-delay-2.5' },
		{ label: '3s', value: 'gsap-delay-3' },
		{ label: '3.5s', value: 'gsap-delay-3.5' },
		{ label: '4s', value: 'gsap-delay-4' },
		{ label: '4.5s', value: 'gsap-delay-4.5' },
		{ label: '5s', value: 'gsap-delay-5' },
    ],
    'animation-ease': [
        { label: 'None', value: '' },
        { label: 'Ease Out', value: 'gsap-ease-power2.out' },
        { label: 'Ease In', value: 'gsap-ease-power2.in' },
        { label: 'Bounce', value: 'gsap-ease-bounce.out' },
        { label: 'Elastic', value: 'gsap-ease-elastic.out' }
    ],
    'stagger-delay': [
        { label: 'None', value: '' },
        { label: '0.1s', value: 'stagger-0.1' },
        { label: '0.2s', value: 'stagger-0.2' },
        { label: '0.3s', value: 'stagger-0.3' },
        { label: '0.5s', value: 'stagger-0.5' },
		{ label: '0.75s', value: 'stagger-0.75' },
		{ label: '1s', value: 'stagger-1' },
		{ label: '1.5s', value: 'stagger-1.5' },
		{ label: '2s', value: 'stagger-2' },
		{ label: '2.5s', value: 'stagger-2.5' },
		
    ],
    'stagger-group': [
        { label: 'None', value: '' },
        { label: 'Group 1', value: 'stagger-group-1' },
        { label: 'Group 2', value: 'stagger-group-2' },
        { label: 'Group 3', value: 'stagger-group-3' },
		{ label: 'Group 4', value: 'stagger-group-4' },
		{ label: 'Group 5', value: 'stagger-group-5' },
		{ label: 'Group 6', value: 'stagger-group-6' },
		{ label: 'Group 7', value: 'stagger-group-7' },
		{ label: 'Group 8', value: 'stagger-group-8' },
		{ label: 'Group 9', value: 'stagger-group-9' },
    ]
};

// Helper function to find animation classes
function getAnimationClass(classes, type) {
    if (!classes) return '';
    const classArray = classes.split(' ');
    switch(type) {
        case 'animation-type':
            return classArray.find(cls => cls.startsWith('gsap-') && !cls.startsWith('gsap-duration-') && !cls.startsWith('gsap-delay-') && !cls.startsWith('gsap-ease-')) || '';
        case 'animation-duration':
            return classArray.find(cls => cls.startsWith('gsap-duration-')) || '';
        case 'animation-delay':
            return classArray.find(cls => cls.startsWith('gsap-delay-')) || '';
        case 'animation-ease':
            return classArray.find(cls => cls.startsWith('gsap-ease-')) || '';
        case 'stagger-delay':
            return classArray.find(cls => cls.startsWith('stagger-') && !cls.startsWith('stagger-group-')) || '';
        case 'stagger-group':
            return classArray.find(cls => cls.startsWith('stagger-group-')) || '';
        default:
            return '';
    }
}

// Helper function to update animation classes
function updateAnimationClasses(className, value, type) {
    const currentClasses = (className || '').split(' ').filter(Boolean);
    
    // Remove existing classes of the same type
    const filteredClasses = currentClasses.filter(cls => {
        switch(type) {
            case 'animation-type':
                return !(cls.startsWith('gsap-') && !cls.startsWith('gsap-duration-') && !cls.startsWith('gsap-delay-') && !cls.startsWith('gsap-ease-'));
            case 'animation-duration':
                return !cls.startsWith('gsap-duration-');
            case 'animation-delay':
                return !cls.startsWith('gsap-delay-');
            case 'animation-ease':
                return !cls.startsWith('gsap-ease-');
            case 'stagger-delay':
                return !cls.startsWith('stagger-') || cls.startsWith('stagger-group-');
            case 'stagger-group':
                return !cls.startsWith('stagger-group-');
            default:
                return true;
        }
    });

    // Add the new value if it exists
    if (value) {
        filteredClasses.push(value);
        if (type === 'animation-type' && !filteredClasses.includes('gsap-scroll')) {
            filteredClasses.push('gsap-scroll');
        }
    } else if (type === 'animation-type') {
        const scrollIndex = filteredClasses.indexOf('gsap-scroll');
        if (scrollIndex > -1) {
            filteredClasses.splice(scrollIndex, 1);
        }
    }

    return filteredClasses.join(' ').trim();
}

// Add GSAP animation controls
const withGsapControls = createHigherOrderComponent((BlockEdit) => {
    return function (props) {
        const { attributes, setAttributes } = props;
        const className = attributes.className || '';

        const animationPanel = wp.element.createElement(
            wp.components.PanelBody,
            { title: 'Block Animations', initialOpen: false },
            [  // Wrap controls in an array
                wp.element.createElement(wp.components.SelectControl, {
                    label: 'Animation Type',
                    value: getAnimationClass(className, 'animation-type'),
                    options: blockAnimationOptions['animation-type'],
                    onChange: (value) => setAttributes({ 
                        className: updateAnimationClasses(className, value, 'animation-type') 
                    }),
                    className: getAnimationClass(className, 'animation-type') ? 'has-value' : ''
                }),

                wp.element.createElement(wp.components.SelectControl, {
                    label: 'Duration',
                    value: getAnimationClass(className, 'animation-duration'),
                    options: blockAnimationOptions['animation-duration'],
                    onChange: (value) => setAttributes({ 
                        className: updateAnimationClasses(className, value, 'animation-duration') 
                    }),
                    className: getAnimationClass(className, 'animation-duration') ? 'has-value' : ''
                }),

                wp.element.createElement(wp.components.SelectControl, {
                    label: 'Delay',
                    value: getAnimationClass(className, 'animation-delay'),
                    options: blockAnimationOptions['animation-delay'],
                    onChange: (value) => setAttributes({ 
                        className: updateAnimationClasses(className, value, 'animation-delay') 
                    }),
                    className: getAnimationClass(className, 'animation-delay') ? 'has-value' : ''
                }),

                wp.element.createElement(wp.components.SelectControl, {
                    label: 'Easing',
                    value: getAnimationClass(className, 'animation-ease'),
                    options: blockAnimationOptions['animation-ease'],
                    onChange: (value) => setAttributes({ 
                        className: updateAnimationClasses(className, value, 'animation-ease') 
                    }),
                    className: getAnimationClass(className, 'animation-ease') ? 'has-value' : ''
                }),

                wp.element.createElement(wp.components.SelectControl, {
                    label: 'Stagger Delay',
                    value: getAnimationClass(className, 'stagger-delay'),
                    options: blockAnimationOptions['stagger-delay'],
                    onChange: (value) => setAttributes({ 
                        className: updateAnimationClasses(className, value, 'stagger-delay') 
                    }),
                    className: getAnimationClass(className, 'stagger-delay') ? 'has-value' : ''
                }),

                wp.element.createElement(wp.components.SelectControl, {
                    label: 'Stagger Group',
                    value: getAnimationClass(className, 'stagger-group'),
                    options: blockAnimationOptions['stagger-group'],
                    onChange: (value) => setAttributes({ 
                        className: updateAnimationClasses(className, value, 'stagger-group') 
                    }),
                    className: getAnimationClass(className, 'stagger-group') ? 'has-value' : ''
                })
            ]
        );

        return wp.element.createElement(
            wp.element.Fragment,
            null,
            wp.element.createElement(BlockEdit, props),
            wp.element.createElement(wp.blockEditor.InspectorControls, null, animationPanel)
        );
    };
}, 'withGsapControls');

addFilter('editor.BlockEdit', 'jbs/with-gsap-controls', withGsapControls);