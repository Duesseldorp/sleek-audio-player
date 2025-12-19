(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { useState, useEffect } = wp.element;
    const { SelectControl, PanelBody, Placeholder, Spinner } = wp.components;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { __ } = wp.i18n;

    registerBlockType('simple-audio-player/player', {
        title: __('Simple Audio Player', 'simple-audio-player'),
        description: __('Fügt einen Audio-Player mit Playlist hinzu.', 'simple-audio-player'),
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

            // Playlists laden
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

            // Vorschau laden wenn Playlist gewählt
            useEffect(function() {
                if (playlistId) {
                    setPreview('');
                    wp.apiFetch({ path: '/sap/v1/preview/' + playlistId })
                        .then(function(html) {
                            setPreview(html);
                        });
                }
            }, [playlistId]);

            // Playlist-Optionen für Dropdown
            var options = [{ label: __('-- Playlist wählen --', 'simple-audio-player'), value: '' }];
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
                        { title: __('Player Einstellungen', 'simple-audio-player'), initialOpen: true },
                        wp.element.createElement(SelectControl, {
                            label: __('Playlist', 'simple-audio-player'),
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
                        { icon: 'playlist-audio', label: __('Simple Audio Player', 'simple-audio-player') },
                        wp.element.createElement(Spinner)
                    )
                    : !playlistId
                        ? wp.element.createElement(
                            Placeholder,
                            { 
                                icon: 'playlist-audio', 
                                label: __('Simple Audio Player', 'simple-audio-player'),
                                instructions: __('Wähle eine Playlist aus der Sidebar.', 'simple-audio-player')
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
                                'Simple Audio Player – Playlist #' + playlistId
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
                                    : __('Player-Vorschau wird im Frontend angezeigt.', 'simple-audio-player')
                            )
                        )
            );
        },

        save: function() {
            // Dynamischer Block - Rendering via PHP
            return null;
        }
    });
})(window.wp);
