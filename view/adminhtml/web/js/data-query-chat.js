define(['jquery'], function ($) {
    'use strict';

    var STORAGE_KEY = 'gtstudio_data_query_chat';
    var MAX_STORED  = 50;

    function loadState(defaultModel) {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            return raw ? JSON.parse(raw) : defaultState(defaultModel);
        } catch (e) {
            return defaultState(defaultModel);
        }
    }

    function defaultState(defaultModel) {
        return { messages: [], totalTokens: 0, model: defaultModel || '', provider: 'anthropic' };
    }

    function saveState(state) {
        try {
            if (state.messages.length > MAX_STORED) {
                state.messages = state.messages.slice(-MAX_STORED);
            }
            localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        } catch (e) { /* localStorage unavailable */ }
    }

    function escapeHtml(text) {
        return $('<div>').text(text).html();
    }

    /**
     * Render a single message (user or assistant).
     *
     * Assistant messages may have a `data` field containing the raw query result
     * from the server. This is displayed as a pre-formatted data block directly
     * below the explanation — the data never left the server to reach the LLM.
     */
    function renderMessage(msg) {
        var html = '';

        if (msg.role === 'user') {
            html = '<div class="gtaidq-msg gtaidq-msg--user">' + escapeHtml(msg.content) + '</div>';
        } else {
            var content = escapeHtml(msg.content);
            content = content.replace(
                /(https?:\/\/[^\s]+)/g,
                '<a href="$1" target="_blank" rel="noopener noreferrer" style="text-decoration:underline;opacity:.9;">$1</a>'
            );

            html = '<div class="gtaidq-msg gtaidq-msg--assistant">' + content + '</div>';

            if (msg.data) {
                html += '<div class="gtaidq-data-block">'
                    + '<div class="gtaidq-data-label">Query result <span class="gtaidq-data-badge">local data — not sent to AI</span></div>'
                    + '<pre class="gtaidq-data-pre">' + escapeHtml(msg.data) + '</pre>'
                    + '</div>';
            }
        }

        return html;
    }

    function calculateCost(tokens, model, pricingTable) {
        model = (model || '').toLowerCase();
        var table = pricingTable || {};
        if (!Object.prototype.hasOwnProperty.call(table, model)) {
            return null; // unknown model — price not available
        }
        return tokens * table[model] / 1000;
    }

    function formatCost(cost) {
        if (cost === null || cost === undefined) { return '—'; }
        var n = parseFloat(cost) || 0;
        if (n === 0) { return '$0.00'; }
        if (n < 0.01) { return '$' + n.toFixed(4); }
        return '$' + n.toFixed(2);
    }

    function updateTokenDisplay(state, $tokenEl, $costEl, pricingTable) {
        var total = state.totalTokens || 0;
        $tokenEl.text(total.toLocaleString());
        $costEl.text(formatCost(calculateCost(total, state.model, pricingTable)));
    }

    function showWelcome($messages) {
        $messages.html(
            '<div class="gtaidq-welcome">' +
            '  <div class="gtaidq-welcome-icon">⚡</div>' +
            '  <p>Welcome to Data Query Assistant</p>' +
            '  <small>Ask natural language questions about your store data.</small>' +
            '  <div class="gtaidq-chips">' +
            '    <span class="gtaidq-chip" data-query="Top 10 products by revenue this month">📦 Top products by revenue</span>' +
            '    <span class="gtaidq-chip" data-query="Order status breakdown">📋 Order status breakdown</span>' +
            '    <span class="gtaidq-chip" data-query="Low inventory alert — products below 10 units">⚠️ Low inventory alert</span>' +
            '    <span class="gtaidq-chip" data-query="Customer acquisition trends last 6 months">👥 Acquisition trends</span>' +
            '  </div>' +
            '</div>'
        );
    }

    function downloadFile(content, filename, mimeType) {
        var blob = new Blob([content], { type: mimeType });
        var url  = URL.createObjectURL(blob);
        var a    = document.createElement('a');
        a.href     = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    return function (config) {
        var endpointUrl  = config.endpointUrl;
        var formKey      = config.formKey;
        var pricingTable = config.pricingTable || {};
        var defaultModel = config.defaultModel || '';

        var $messages  = $('#gtaidq-messages');
        var $input     = $('#gtaidq-input');
        var $send      = $('#gtaidq-send');
        var $clearBtn  = $('#gtaidq-clear');
        var $exportBtn = $('#gtaidq-export');
        var $tokenEl   = $('#gtaidq-token-count');
        var $costEl    = $('#gtaidq-estimated-cost');

        var $modal          = $('#gtaidq-export-modal');
        var $modalClose     = $('#gtaidq-modal-close');
        var $modalCancel    = $('#gtaidq-modal-cancel');
        var $modalDownload  = $('#gtaidq-modal-download');
        var $exportFilename = $('#gtaidq-filename-input');

        var state = loadState(defaultModel);

        // Restore previous messages from localStorage (display only — not re-sent to LLM)
        if (state.messages.length) {
            $messages.empty();
            $.each(state.messages, function (_, msg) {
                $messages.append(renderMessage(msg));
            });
            $messages.scrollTop($messages[0].scrollHeight);
        }

        updateTokenDisplay(state, $tokenEl, $costEl, pricingTable);

        $messages.on('click', '.gtaidq-chip', function () {
            var query = $(this).data('query');
            if (query) {
                $input.val(query).trigger('focus');
                sendMessage();
            }
        });

        $clearBtn.on('click', function (e) {
            e.preventDefault();
            if (!confirm('Clear all messages? This cannot be undone.')) {
                return;
            }
            state.messages    = [];
            state.totalTokens = 0;
            showWelcome($messages);
            updateTokenDisplay(state, $tokenEl, $costEl, pricingTable);
            saveState(state);
        });

        $exportBtn.on('click', function (e) {
            e.preventDefault();
            if (!state.messages.length) {
                alert('No conversation to export.');
                return;
            }
            $('#gtaidq-export-msg-count').text(state.messages.length);
            $('#gtaidq-export-token-count').text((state.totalTokens || 0).toLocaleString());
            $('#gtaidq-export-cost-display').text(formatCost(calculateCost(state.totalTokens || 0, state.model, pricingTable)));
            $modal.addClass('active');
        });

        function closeModal() { $modal.removeClass('active'); }

        $modalClose.on('click', closeModal);
        $modalCancel.on('click', closeModal);
        $modal.on('click', function (e) { if (e.target === this) { closeModal(); } });

        $modalDownload.on('click', function (e) {
            e.preventDefault();
            var format   = $('input[name="gtaidq-export-format"]:checked').val() || 'json';
            var filename = ($exportFilename.val().trim() || 'data-query-conversation') + '.' + format;
            var meta     = {
                model:          state.model,
                provider:       state.provider,
                total_tokens:   state.totalTokens,
                estimated_cost: formatCost(calculateCost(state.totalTokens, state.model, pricingTable)),
                privacy_note:   'Store data was queried locally and never sent to the AI provider.'
            };
            var content;

            if (format === 'json') {
                content = JSON.stringify({ meta: meta, messages: state.messages }, null, 2);
                downloadFile(content, filename, 'application/json');
            } else {
                var lines = ['Data Query Conversation Export', '================================', ''];
                lines.push('Model: ' + meta.model);
                lines.push('Provider: ' + meta.provider);
                lines.push('Total Tokens: ' + meta.total_tokens);
                lines.push('Estimated Cost: ' + meta.estimated_cost);
                lines.push('Privacy: ' + meta.privacy_note);
                lines.push('');
                $.each(state.messages, function (_, msg) {
                    lines.push('[' + msg.role.toUpperCase() + '] ' + (msg.timestamp || ''));
                    lines.push(msg.content);
                    if (msg.data) {
                        lines.push('--- Query Result ---');
                        lines.push(msg.data);
                        lines.push('--------------------');
                    }
                    lines.push('');
                });
                content = lines.join('\n');
                downloadFile(content, filename, 'text/plain');
            }
            closeModal();
        });

        /**
         * Send the user's message to the backend.
         *
         * Only the message text is sent — no conversation history is included.
         * The backend uses the LLM solely to determine which tool to call and
         * with what parameters (structured output). The tool runs on the server
         * and the raw data is returned directly in the response. It is displayed
         * here without any further LLM processing.
         */
        function sendMessage() {
            var text = $input.val().trim();
            if (!text) { return; }

            var userMsg = { role: 'user', content: text, timestamp: new Date().toISOString() };
            state.messages.push(userMsg);

            $messages.html('');
            $.each(state.messages, function (_, msg) { $messages.append(renderMessage(msg)); });
            $messages.scrollTop($messages[0].scrollHeight);

            $input.val('');
            $send.prop('disabled', true).html(
                '<svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24" style="animation:spin 1s linear infinite;">' +
                '<circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2" opacity=".3"/>' +
                '<circle cx="12" cy="2" r="1.5" fill="currentColor"/>' +
                '</svg>'
            );

            $.ajax({
                url:      endpointUrl,
                type:     'POST',
                dataType: 'json',
                // Note: only message + form_key — no history sent to backend/LLM
                data: {
                    message:  text,
                    form_key: formKey
                }
            }).done(function (response) {
                var content = (response && response.success && response.content)
                    ? response.content
                    : 'No response received.';

                // Estimate tokens from the intent call only (not data — data never went to LLM)
                var tokens = response && response.tokens
                    ? parseInt(response.tokens, 10)
                    : Math.ceil(text.length / 4);
                state.totalTokens += tokens;

                if (response && response.model)    { state.model    = response.model; }
                if (response && response.provider) { state.provider = response.provider; }

                // Store data in localStorage for display on reload — it stays local
                var assistantMsg = {
                    role:      'assistant',
                    content:   content,
                    data:      response && response.data ? response.data : null,
                    tool:      response && response.tool ? response.tool : null,
                    timestamp: new Date().toISOString(),
                    tokens:    tokens
                };

                state.messages.push(assistantMsg);
                $messages.append(renderMessage(assistantMsg));
                $messages.scrollTop($messages[0].scrollHeight);
                updateTokenDisplay(state, $tokenEl, $costEl, pricingTable);
                saveState(state);
            }).fail(function () {
                var errMsg = {
                    role:      'assistant',
                    content:   'Request failed. Please try again.',
                    data:      null,
                    timestamp: new Date().toISOString()
                };
                state.messages.push(errMsg);
                $messages.append(renderMessage(errMsg));
                $messages.scrollTop($messages[0].scrollHeight);
                saveState(state);
            }).always(function () {
                $send.prop('disabled', false).html(
                    '<svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">' +
                    '<path d="M2 21l21-9L2 3v7l15 2-15 2z"/>' +
                    '</svg>'
                );
            });
        }

        $send.on('click', sendMessage);

        $input.on('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        $input.on('input', function () {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 110) + 'px';
        });
    };
});
