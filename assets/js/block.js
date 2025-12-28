/**
 * Sleek Audio Player - Gutenberg Block
 * 
 * @author Martin Gräbing
 * @link https://www.duesseldorp.de
 * @license GPL-2.0-or-later
 */

(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { useState, useEffect } = wp.element;
    const { SelectControl, PanelBody, Placeholder, Spinner } = wp.components;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { __ } = wp.i18n;

    registerBlockType('sleek-audio-player/player', {
        title: __('Sleek Audio Player', 'sleek-audio-player'),
        description: __('Adds an audio player with playlist.', 'sleek-audio-player'),
        category: 'media',
        icon: 'playlist-audio',
        keywords: [__('audio'), __('music'), __('player'), __('playlist'), __('mp3')],
        
        attributes: {
            playlistId: {
                type: 'string',
                default: ''
            }
        },

        edit: function(props) {
            const { attributes, setAttributes } = props;
            const { playlistId } = attributes;
            const blockProps = useBlockProps();
            const [playlists, setPlaylists] = useState([]);
            const [loading, setLoading] = useState(true);
            const [preview, setPreview] = useState('');

            // Load playlists
            useEffect(function() {
                wp.apiFetch({ path: '/sap/v1/playlists' })
                    .then(function(data) {
                        setPlaylists(data);
                        setLoading(false);
                    })
                    .catch(function() {
                        setLoading(false);
                    });
            }, []);

            // Load preview when playlist selected
            useEffect(function() {
                if (playlistId) {
                    setPreview('');
                    wp.apiFetch({ path: '/sap/v1/preview/' + playlistId })
                        .then(function(html) {
                            setPreview(html);
                        });
                }
            }, [playlistId]);

            // Playlist options for dropdown
            var options = [{ label: __('-- Select Playlist --', 'sleek-audio-player'), value: '' }];
            playlists.forEach(function(pl) {
                options.push({ label: pl.name + ' (' + pl.count + ' Tracks)', value: pl.id.toString() });
            });

            return wp.element.createElement(
                'div',
                blockProps,
                // Inspector Controls (Sidebar)
                wp.element.createElement(
                    InspectorControls,
                    null,
                    wp.element.createElement(
                        PanelBody,
                        { title: __('Player Settings', 'sleek-audio-player'), initialOpen: true },
                        wp.element.createElement(SelectControl, {
                            label: __('Playlist', 'sleek-audio-player'),
                            value: playlistId,
                            options: options,
                            onChange: function(value) {
                                setAttributes({ playlistId: value });
                            }
                        })
                    )
                ),
                // Block Content
                loading
                    ? wp.element.createElement(
                        Placeholder,
                        { icon: 'playlist-audio', label: __('Sleek Audio Player', 'sleek-audio-player') },
                        wp.element.createElement(Spinner)
                    )
                    : !playlistId
                        ? wp.element.createElement(
                            Placeholder,
                            { 
                                icon: 'playlist-audio', 
                                label: __('Sleek Audio Player', 'sleek-audio-player'),
                                instructions: __('Select a playlist from the sidebar.', 'sleek-audio-player')
                            },
                            wp.element.createElement(SelectControl, {
                                value: playlistId,
                                options: options,
                                onChange: function(value) {
                                    setAttributes({ playlistId: value });
                                }
                            })
                        )
                        : wp.element.createElement(
                            'div',
                            { className: 'sap-block-preview' },
                            wp.element.createElement('div', { 
                                className: 'sap-block-preview-header',
                                style: { 
                                    padding: '8px 12px', 
                                    background: '#1e1e1e', 
                                    color: '#fff',
                                    fontSize: '12px',
                                    borderRadius: '4px 4px 0 0',
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: '8px'
                                }
                            },
                                wp.element.createElement('span', { 
                                    className: 'dashicons dashicons-playlist-audio',
                                    style: { fontSize: '16px' }
                                }),
                                'Sleek Audio Player – Playlist #' + playlistId
                            ),
                            wp.element.createElement('div', {
                                style: {
                                    padding: '20px',
                                    background: '#2d2d2c',
                                    borderRadius: '0 0 4px 4px',
                                    textAlign: 'center',
                                    color: '#999'
                                }
                            }, 
                                preview 
                                    ? wp.element.createElement('div', { dangerouslySetInnerHTML: { __html: preview } })
                                    : __('Player preview is shown in the frontend.', 'sleek-audio-player')
                            )
                        )
            );
        },

        save: function() {
            // Dynamic block - Rendering via PHP
            return null;
        }
    });
})(window.wp);
