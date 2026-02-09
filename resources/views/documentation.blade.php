<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #f8fafc;
            color: #334155;
            line-height: 1.6;
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: #1e293b;
            color: #cbd5e1;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            overflow-y: auto;
            padding: 24px 0;
            z-index: 10;
        }
        .sidebar-title {
            color: #f1f5f9;
            font-size: 18px;
            font-weight: 700;
            padding: 0 20px 20px;
            border-bottom: 1px solid #334155;
        }
        .sidebar-group { margin-top: 16px; }
        .sidebar-group-name {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            padding: 0 20px 6px;
        }
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 20px;
            color: #94a3b8;
            text-decoration: none;
            font-size: 13px;
            transition: background 0.15s, color 0.15s;
        }
        .sidebar-link:hover, .sidebar-link.active {
            background: #334155;
            color: #f1f5f9;
        }

        /* Method badges */
        .method-badge {
            display: inline-block;
            font-size: 10px;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 3px;
            text-transform: uppercase;
            min-width: 42px;
            text-align: center;
            font-family: monospace;
        }
        .method-get { background: #065f46; color: #6ee7b7; }
        .method-post { background: #1e40af; color: #93c5fd; }
        .method-put { background: #92400e; color: #fcd34d; }
        .method-patch { background: #581c87; color: #d8b4fe; }
        .method-delete { background: #991b1b; color: #fca5a5; }

        /* Main content */
        .main {
            margin-left: 280px;
            flex: 1;
            padding: 32px 40px;
            max-width: 960px;
        }
        .page-title { font-size: 28px; font-weight: 700; color: #0f172a; margin-bottom: 8px; }
        .page-description { color: #64748b; margin-bottom: 8px; }
        .base-url { font-size: 14px; color: #64748b; margin-bottom: 32px; }
        .base-url code {
            background: #e2e8f0;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 13px;
            color: #334155;
        }

        /* Endpoint card */
        .endpoint {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .endpoint-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
        }
        .endpoint-header .method-badge { font-size: 12px; padding: 3px 10px; }
        .endpoint-url {
            font-family: monospace;
            font-size: 14px;
            color: #0f172a;
            font-weight: 500;
        }
        .endpoint-auth {
            margin-left: auto;
            font-size: 11px;
            background: #fef3c7;
            color: #92400e;
            padding: 2px 8px;
            border-radius: 3px;
            font-weight: 600;
        }
        .endpoint-body { padding: 20px; }

        /* Section headers */
        .section-title {
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #64748b;
            margin: 16px 0 8px;
        }
        .section-title:first-child { margin-top: 0; }

        /* Parameter tables */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            margin-bottom: 12px;
        }
        th {
            text-align: left;
            font-weight: 600;
            color: #475569;
            padding: 8px 12px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        td {
            padding: 8px 12px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
        }
        td code {
            background: #f1f5f9;
            padding: 1px 5px;
            border-radius: 3px;
            font-size: 12px;
        }
        .badge-required {
            font-size: 10px;
            font-weight: 600;
            color: #dc2626;
            background: #fef2f2;
            padding: 1px 6px;
            border-radius: 3px;
        }
        .badge-optional {
            font-size: 10px;
            font-weight: 600;
            color: #64748b;
            background: #f1f5f9;
            padding: 1px 6px;
            border-radius: 3px;
        }

        /* Code blocks */
        .code-block {
            background: #1e293b;
            color: #e2e8f0;
            padding: 16px;
            border-radius: 6px;
            overflow-x: auto;
            font-family: monospace;
            font-size: 13px;
            line-height: 1.5;
            white-space: pre-wrap;
            word-break: break-word;
            margin-bottom: 12px;
        }

        /* Response tabs */
        .response-tabs {
            display: flex;
            gap: 4px;
            margin-bottom: 8px;
        }
        .response-tab {
            padding: 4px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            background: #f8fafc;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            transition: all 0.15s;
        }
        .response-tab:hover { background: #e2e8f0; }
        .response-tab.active {
            background: #1e293b;
            color: #f1f5f9;
            border-color: #1e293b;
        }
        .response-panel { display: none; }
        .response-panel.active { display: block; }
        .response-description {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 8px;
        }

        /* Group header */
        .group-header {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            margin: 32px 0 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e2e8f0;
        }
        .group-header:first-of-type { margin-top: 0; }
    </style>
</head>
<body>
    <nav class="sidebar">
        <div class="sidebar-title">{{ $title }}</div>
        @foreach ($groups as $groupName => $endpoints)
            <div class="sidebar-group">
                <div class="sidebar-group-name">{{ $groupName }}</div>
                @foreach ($endpoints as $index => $endpoint)
                    <a href="#{{ Str::slug($groupName . '-' . $endpoint['uri'] . '-' . $endpoint['method']) }}"
                       class="sidebar-link">
                        <span class="method-badge method-{{ strtolower($endpoint['method']) }}">{{ $endpoint['method'] }}</span>
                        <span>/{{ ltrim($endpoint['uri'], '/') }}</span>
                    </a>
                @endforeach
            </div>
        @endforeach
    </nav>

    <main class="main">
        <h1 class="page-title">{{ $title }}</h1>
        @if ($description)
            <p class="page-description">{{ $description }}</p>
        @endif
        <p class="base-url">Base URL: <code>{{ $base_url }}</code></p>

        @foreach ($groups as $groupName => $endpoints)
            <h2 class="group-header" id="group-{{ Str::slug($groupName) }}">{{ $groupName }}</h2>

            @foreach ($endpoints as $endpoint)
                <div class="endpoint" id="{{ Str::slug($groupName . '-' . $endpoint['uri'] . '-' . $endpoint['method']) }}">
                    <div class="endpoint-header">
                        <span class="method-badge method-{{ strtolower($endpoint['method']) }}">{{ $endpoint['method'] }}</span>
                        <span class="endpoint-url">{{ $endpoint['full_url'] }}</span>
                        @if ($endpoint['requires_auth'])
                            <span class="endpoint-auth">Requires Auth</span>
                        @endif
                    </div>
                    <div class="endpoint-body">
                        {{-- URL Parameters --}}
                        @if (! empty($endpoint['parameters']))
                            <div class="section-title">URL Parameters</div>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Required</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($endpoint['parameters'] as $param)
                                        <tr>
                                            <td><code>{{ $param['name'] }}</code></td>
                                            <td>
                                                @if ($param['required'])
                                                    <span class="badge-required">Required</span>
                                                @else
                                                    <span class="badge-optional">Optional</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif

                        {{-- Request Body Fields --}}
                        @if (! empty($endpoint['fields']))
                            <div class="section-title">Request Body</div>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Rules</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($endpoint['fields'] as $field)
                                        <tr>
                                            <td><code>{{ $field['name'] }}</code></td>
                                            <td>{{ $field['type'] }}</td>
                                            <td>
                                                @if ($field['required'])
                                                    <span class="badge-required">Required</span>
                                                @else
                                                    <span class="badge-optional">Optional</span>
                                                @endif
                                            </td>
                                            <td>{{ implode(', ', $field['rules'] ?? []) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif

                        {{-- Curl Example --}}
                        <div class="section-title">Example Request</div>
                        <div class="code-block">{{ $endpoint['curl'] }}</div>

                        {{-- Response Examples --}}
                        @if (! empty($endpoint['responses']))
                            <div class="section-title">Responses</div>
                            <div class="response-tabs">
                                @foreach ($endpoint['responses'] as $rIndex => $response)
                                    <button class="response-tab {{ $rIndex === 0 ? 'active' : '' }}"
                                            data-target="{{ Str::slug($groupName . '-' . $endpoint['uri'] . '-' . $endpoint['method']) }}-resp-{{ $rIndex }}">
                                        {{ $response['status'] }}
                                    </button>
                                @endforeach
                            </div>
                            @foreach ($endpoint['responses'] as $rIndex => $response)
                                <div class="response-panel {{ $rIndex === 0 ? 'active' : '' }}"
                                     id="{{ Str::slug($groupName . '-' . $endpoint['uri'] . '-' . $endpoint['method']) }}-resp-{{ $rIndex }}">
                                    @if (! empty($response['description']))
                                        <div class="response-description">{{ $response['description'] }}</div>
                                    @endif
                                    @if (! empty($response['content']))
                                        <div class="code-block">{{ json_encode($response['content'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</div>
                                    @endif
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            @endforeach
        @endforeach
    </main>

    <script>
        // Response tab switching
        document.querySelectorAll('.response-tab').forEach(function(tab) {
            tab.addEventListener('click', function() {
                var parent = this.closest('.endpoint-body');
                parent.querySelectorAll('.response-tab').forEach(function(t) { t.classList.remove('active'); });
                parent.querySelectorAll('.response-panel').forEach(function(p) { p.classList.remove('active'); });
                this.classList.add('active');
                document.getElementById(this.dataset.target).classList.add('active');
            });
        });

        // Active sidebar link on scroll
        var links = document.querySelectorAll('.sidebar-link');
        var sections = [];
        links.forEach(function(link) {
            var id = link.getAttribute('href').substring(1);
            var el = document.getElementById(id);
            if (el) sections.push({ el: el, link: link });
        });
        window.addEventListener('scroll', function() {
            var scrollY = window.scrollY + 80;
            var active = null;
            sections.forEach(function(s) {
                if (s.el.offsetTop <= scrollY) active = s;
            });
            links.forEach(function(l) { l.classList.remove('active'); });
            if (active) active.link.classList.add('active');
        });
    </script>
</body>
</html>
