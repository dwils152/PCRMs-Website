// Minimal local runtime for the .dc.html design mockups in this folder.
// Renders the <x-dc> template ({{holes}}, <sc-for>, <sc-if>, onClick/onChange)
// so the mockups open directly in a browser without the design canvas.
(function () {
    'use strict';

    var hideStyle = document.createElement('style');
    hideStyle.textContent = 'x-dc{display:none}';
    document.head.appendChild(hideStyle);

    var HOLE = /\{\{\s*([^}]+?)\s*\}\}/g;
    var WHOLE_HOLE = /^\s*\{\{\s*([^}]+?)\s*\}\}\s*$/;

    function DCLogic(props) {
        this.props = props || {};
        this.state = undefined;
    }
    DCLogic.prototype.setState = function (patch) {
        this.state = Object.assign({}, this.state || {}, patch);
        if (this.__render) {
            this.__render();
        }
    };
    DCLogic.prototype.forceUpdate = function () {
        if (this.__render) {
            this.__render();
        }
    };

    function lookup(path, scope) {
        if (path === 'true') return true;
        if (path === 'false') return false;
        if (/^-?\d+(\.\d+)?$/.test(path)) return Number(path);
        var parts = path.split('.');
        var value = scope;
        for (var i = 0; i < parts.length; i++) {
            if (value === null || value === undefined) return undefined;
            value = value[parts[i]];
        }
        return value;
    }

    function interpolate(text, scope) {
        return text.replace(HOLE, function (match, path) {
            var value = lookup(path, scope);
            return value === null || value === undefined ? '' : String(value);
        });
    }

    function renderNodes(nodes, scope, parent) {
        for (var i = 0; i < nodes.length; i++) {
            renderNode(nodes[i], scope, parent);
        }
    }

    function renderNode(node, scope, parent) {
        if (node.nodeType === Node.TEXT_NODE) {
            parent.appendChild(document.createTextNode(interpolate(node.nodeValue, scope)));
            return;
        }
        if (node.nodeType !== Node.ELEMENT_NODE) {
            return;
        }

        var tag = node.localName;
        if (tag === 'helmet') {
            return;
        }
        if (tag === 'sc-for') {
            var list = lookup((node.getAttribute('list') || '').replace(/[{}\s]/g, ''), scope) || [];
            var alias = node.getAttribute('as') || 'item';
            for (var index = 0; index < list.length; index++) {
                var childScope = Object.create(scope);
                childScope[alias] = list[index];
                childScope.$index = index;
                renderNodes(node.childNodes, childScope, parent);
            }
            return;
        }
        if (tag === 'sc-if') {
            var condition = lookup((node.getAttribute('value') || '').replace(/[{}\s]/g, ''), scope);
            if (condition) {
                renderNodes(node.childNodes, scope, parent);
            }
            return;
        }

        var element = node.namespaceURI && node.namespaceURI !== 'http://www.w3.org/1999/xhtml'
            ? document.createElementNS(node.namespaceURI, tag)
            : document.createElement(tag);

        for (var a = 0; a < node.attributes.length; a++) {
            var attribute = node.attributes[a];
            var name = attribute.name;
            var raw = attribute.value;
            if (name.indexOf('hint-') === 0) {
                continue;
            }
            var whole = raw.match(WHOLE_HOLE);
            if (name.indexOf('on') === 0 && whole) {
                var handler = lookup(whole[1], scope);
                if (typeof handler === 'function') {
                    element.addEventListener(name.slice(2).toLowerCase(), handler);
                }
                continue;
            }
            if (name === 'checked') {
                var checked = whole ? !!lookup(whole[1], scope) : true;
                element.defaultChecked = checked;
                element.checked = checked;
                continue;
            }
            element.setAttribute(name, interpolate(raw, scope));
        }

        renderNodes(node.childNodes, scope, element);
        parent.appendChild(element);
    }

    function boot() {
        var host = document.querySelector('x-dc');
        var script = document.querySelector('script[data-dc-script]');
        if (!host || !script) {
            return;
        }

        var helmet = host.querySelector('helmet');
        if (helmet) {
            Array.prototype.slice.call(helmet.childNodes).forEach(function (child) {
                document.head.appendChild(child);
            });
        }

        var template = host.cloneNode(true);
        var props = {};
        try {
            var declared = JSON.parse(script.getAttribute('data-props') || '{}');
            Object.keys(declared).forEach(function (key) {
                if (declared[key] && Object.prototype.hasOwnProperty.call(declared[key], 'default')) {
                    props[key] = declared[key].default;
                }
            });
        } catch (error) {
            console.error('Could not read data-props', error);
        }

        var Component = new Function('DCLogic', script.textContent + '\nreturn Component;')(DCLogic);
        var instance = new Component(props);
        instance.props = props;

        instance.__render = function () {
            // Re-rendering replaces every element, so remember which one had
            // focus (by id) and its text selection, and restore them after.
            var active = document.activeElement;
            var focusId = active && host.contains(active) ? active.id : '';
            var selection = null;
            if (focusId && typeof active.selectionStart === 'number') {
                selection = [active.selectionStart, active.selectionEnd];
            }

            var fragment = document.createDocumentFragment();
            renderNodes(template.childNodes, instance.renderVals(), fragment);
            host.replaceChildren(fragment);

            if (focusId) {
                var next = document.getElementById(focusId);
                if (next) {
                    next.focus();
                    if (selection && typeof next.setSelectionRange === 'function') {
                        next.setSelectionRange(selection[0], selection[1]);
                    }
                }
            }
        };

        instance.__render();
        host.style.display = 'block';
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}());
