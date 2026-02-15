/**
 * FreeNav Builder - Menu node builder UI
 */
(function() {
    'use strict';

    if (typeof Craft === 'undefined') {
        return;
    }

    const FreeNavBuilder = {
        menuId: null,
        maxLevels: null,
        maxNodes: null,
        siteId: null,

        init: function() {
            const builder = document.getElementById('freenav-builder');
            if (!builder) return;

            this.menuId = builder.dataset.menuId;
            this.maxLevels = builder.dataset.maxLevels ? parseInt(builder.dataset.maxLevels) : null;
            this.maxNodes = builder.dataset.maxNodes ? parseInt(builder.dataset.maxNodes) : null;
            this.siteId = builder.dataset.siteId;

            this._bindAddNode();
            this._bindEditNodes();
            this._bindDeleteNodes();
            this._bindToggleNodes();
            this._bindDragDrop();
            this._bindPanels();
        },

        _bindAddNode: function() {
            const btn = document.getElementById('freenav-add-node-btn');
            if (!btn) return;

            btn.addEventListener('click', () => {
                this._showPanel('freenav-add-panel');
            });

            const submitBtn = document.getElementById('freenav-add-submit');
            if (submitBtn) {
                submitBtn.addEventListener('click', () => {
                    this._submitAddNode();
                });
            }

            const cancelBtn = document.getElementById('freenav-add-cancel');
            if (cancelBtn) {
                cancelBtn.addEventListener('click', () => {
                    this._hidePanel('freenav-add-panel');
                });
            }

            // Toggle URL/element fields based on type
            const typeSelect = document.getElementById('freenav-node-type');
            if (typeSelect) {
                typeSelect.addEventListener('change', () => {
                    this._updateTypeFields(typeSelect.value, typeSelect.selectedOptions[0]);
                });
            }
        },

        _bindEditNodes: function() {
            document.querySelectorAll('.freenav-node-edit').forEach(btn => {
                btn.addEventListener('click', () => {
                    this._loadEditNode(btn.dataset.nodeId);
                });
            });

            const submitBtn = document.getElementById('freenav-edit-submit');
            if (submitBtn) {
                submitBtn.addEventListener('click', () => {
                    this._submitEditNode();
                });
            }

            const cancelBtn = document.getElementById('freenav-edit-cancel');
            if (cancelBtn) {
                cancelBtn.addEventListener('click', () => {
                    this._hidePanel('freenav-edit-panel');
                });
            }
        },

        _bindDeleteNodes: function() {
            document.querySelectorAll('.freenav-node-delete').forEach(btn => {
                btn.addEventListener('click', () => {
                    if (confirm(Craft.t('free-nav', 'Are you sure you want to delete this node?'))) {
                        this._deleteNode(btn.dataset.nodeId);
                    }
                });
            });
        },

        _bindToggleNodes: function() {
            document.querySelectorAll('.freenav-node-toggle').forEach(btn => {
                btn.addEventListener('click', () => {
                    const enabled = btn.dataset.enabled === '1';
                    this._toggleNode(btn.dataset.nodeId, !enabled, btn);
                });
            });
        },

        _bindDragDrop: function() {
            const nodes = document.querySelectorAll('.freenav-node');

            nodes.forEach(node => {
                const drag = node.querySelector('.freenav-node-drag');
                if (!drag) return;

                drag.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    this._startDrag(node, e);
                });
            });
        },

        _startDrag: function(node, startEvent) {
            const nodes = document.querySelectorAll('.freenav-node');
            let targetNode = null;

            node.classList.add('is-dragging');

            const onMouseMove = (e) => {
                nodes.forEach(n => {
                    n.classList.remove('drag-over', 'drag-over-child');
                });

                const hoveredNode = this._getNodeAtPosition(e.clientY);
                if (hoveredNode && hoveredNode !== node) {
                    const rect = hoveredNode.getBoundingClientRect();
                    const midY = rect.top + rect.height / 2;

                    if (e.clientY < midY) {
                        hoveredNode.classList.add('drag-over');
                    } else {
                        hoveredNode.classList.add('drag-over');
                    }
                    targetNode = hoveredNode;
                }
            };

            const onMouseUp = () => {
                document.removeEventListener('mousemove', onMouseMove);
                document.removeEventListener('mouseup', onMouseUp);

                node.classList.remove('is-dragging');
                document.querySelectorAll('.freenav-node').forEach(n => {
                    n.classList.remove('drag-over', 'drag-over-child');
                });

                if (targetNode && targetNode !== node) {
                    this._moveNode(
                        node.dataset.nodeId,
                        null,
                        targetNode.dataset.nodeId
                    );
                }
            };

            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        },

        _getNodeAtPosition: function(y) {
            const nodes = document.querySelectorAll('.freenav-node:not(.is-dragging)');

            for (const node of nodes) {
                const rect = node.getBoundingClientRect();
                if (y >= rect.top && y <= rect.bottom) {
                    return node;
                }
            }

            return null;
        },

        _bindPanels: function() {
            // Close panels with Escape
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    this._hidePanel('freenav-add-panel');
                    this._hidePanel('freenav-edit-panel');
                }
            });
        },

        _showPanel: function(panelId) {
            const panel = document.getElementById(panelId);
            if (panel) {
                panel.classList.remove('hidden');
                requestAnimationFrame(() => {
                    panel.classList.add('visible');
                });
            }

            this._showOverlay();
        },

        _hidePanel: function(panelId) {
            const panel = document.getElementById(panelId);
            if (panel) {
                panel.classList.remove('visible');
                setTimeout(() => {
                    panel.classList.add('hidden');
                }, 250);
            }

            this._hideOverlay();
        },

        _showOverlay: function() {
            let overlay = document.querySelector('.freenav-panel-overlay');
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.className = 'freenav-panel-overlay';
                overlay.addEventListener('click', () => {
                    this._hidePanel('freenav-add-panel');
                    this._hidePanel('freenav-edit-panel');
                });
                document.body.appendChild(overlay);
            }
            requestAnimationFrame(() => {
                overlay.classList.add('visible');
            });
        },

        _hideOverlay: function() {
            const overlay = document.querySelector('.freenav-panel-overlay');
            if (overlay) {
                overlay.classList.remove('visible');
            }
        },

        _updateTypeFields: function(type, option) {
            const urlField = document.getElementById('freenav-url-field');
            const elementField = document.getElementById('freenav-element-field');
            const isElement = option?.dataset?.isElement === '1';

            if (urlField) {
                urlField.style.display = (type === 'custom' || type === 'site') ? '' : 'none';
            }
            if (elementField) {
                elementField.style.display = isElement ? '' : 'none';
            }
        },

        _submitAddNode: function() {
            const type = document.getElementById('freenav-node-type')?.value || 'custom';
            const title = document.getElementById('freenav-node-title')?.value || '';
            const url = document.getElementById('freenav-node-url')?.value || '';
            const parent = document.getElementById('freenav-node-parent')?.value || '';
            const classes = document.getElementById('freenav-node-classes')?.value || '';
            const icon = document.getElementById('freenav-node-icon')?.value || '';
            const badge = document.getElementById('freenav-node-badge')?.value || '';

            const data = {
                menuId: this.menuId,
                node: {
                    nodeType: type,
                    title: title,
                    url: url || null,
                    parentId: parent || null,
                    classes: classes || null,
                    icon: icon || null,
                    badge: badge || null,
                    newWindow: false,
                    siteId: this.siteId,
                },
            };

            Craft.sendActionRequest('POST', 'free-nav/nodes/add', { data })
                .then(response => {
                    Craft.cp.displayNotice(Craft.t('free-nav', 'Node added.'));
                    this._hidePanel('freenav-add-panel');
                    window.location.reload();
                })
                .catch(error => {
                    Craft.cp.displayError(error?.response?.data?.message || Craft.t('free-nav', 'Could not add node.'));
                });
        },

        _loadEditNode: function(nodeId) {
            // For now, just populate from the DOM and show the panel
            const nodeEl = document.querySelector(`.freenav-node[data-node-id="${nodeId}"]`);
            if (!nodeEl) return;

            const title = nodeEl.querySelector('.freenav-node-title')?.textContent?.trim() || '';

            document.getElementById('freenav-edit-node-id').value = nodeId;
            document.getElementById('freenav-edit-title').value = title;
            document.getElementById('freenav-edit-url').value = '';
            document.getElementById('freenav-edit-classes').value = '';
            document.getElementById('freenav-edit-url-suffix').value = '';
            document.getElementById('freenav-edit-icon').value = '';
            document.getElementById('freenav-edit-badge').value = '';

            this._showPanel('freenav-edit-panel');
        },

        _submitEditNode: function() {
            const nodeId = document.getElementById('freenav-edit-node-id')?.value;
            if (!nodeId) return;

            const data = {
                nodeId: nodeId,
                title: document.getElementById('freenav-edit-title')?.value || '',
                url: document.getElementById('freenav-edit-url')?.value || null,
                classes: document.getElementById('freenav-edit-classes')?.value || null,
                urlSuffix: document.getElementById('freenav-edit-url-suffix')?.value || null,
                icon: document.getElementById('freenav-edit-icon')?.value || null,
                badge: document.getElementById('freenav-edit-badge')?.value || null,
            };

            Craft.sendActionRequest('POST', 'free-nav/nodes/save', { data })
                .then(response => {
                    Craft.cp.displayNotice(Craft.t('free-nav', 'Node saved.'));
                    this._hidePanel('freenav-edit-panel');
                    window.location.reload();
                })
                .catch(error => {
                    Craft.cp.displayError(error?.response?.data?.message || Craft.t('free-nav', 'Could not save node.'));
                });
        },

        _deleteNode: function(nodeId) {
            Craft.sendActionRequest('POST', 'free-nav/nodes/delete', {
                data: { nodeId: nodeId },
            })
                .then(() => {
                    Craft.cp.displayNotice(Craft.t('free-nav', 'Node deleted.'));
                    const nodeEl = document.querySelector(`.freenav-node[data-node-id="${nodeId}"]`);
                    if (nodeEl) {
                        nodeEl.remove();
                    }
                    // Show empty message if no nodes left
                    if (!document.querySelectorAll('.freenav-node').length) {
                        window.location.reload();
                    }
                })
                .catch(error => {
                    Craft.cp.displayError(Craft.t('free-nav', 'Could not delete node.'));
                });
        },

        _toggleNode: function(nodeId, enabled, btn) {
            Craft.sendActionRequest('POST', 'free-nav/nodes/toggle-visibility', {
                data: { nodeId: nodeId, enabled: enabled ? 1 : 0 },
            })
                .then(() => {
                    btn.dataset.enabled = enabled ? '1' : '0';
                    btn.textContent = enabled ? 'On' : 'Off';
                    btn.classList.toggle('inactive', !enabled);
                })
                .catch(() => {
                    Craft.cp.displayError(Craft.t('free-nav', 'Could not toggle node.'));
                });
        },

        _moveNode: function(nodeId, parentId, prevId) {
            Craft.sendActionRequest('POST', 'free-nav/nodes/move-node', {
                data: {
                    nodeId: nodeId,
                    parentId: parentId || null,
                    prevId: prevId || null,
                },
            })
                .then(() => {
                    window.location.reload();
                })
                .catch(() => {
                    Craft.cp.displayError(Craft.t('free-nav', 'Could not move node.'));
                });
        },
    };

    // Initialize when DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => FreeNavBuilder.init());
    } else {
        FreeNavBuilder.init();
    }

    // Expose globally
    window.FreeNavBuilder = FreeNavBuilder;
})();
