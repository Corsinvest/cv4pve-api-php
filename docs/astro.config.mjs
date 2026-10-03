// @ts-check
import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';
import corsinvestTheme from '@corsinvest/cv4pve-docs-theme';

export default defineConfig({
  site: 'https://corsinvest.github.io',
  base: '/cv4pve-api-php',
  integrations: [
    starlight({
      title: 'cv4pve-api-php',
      description: 'Proxmox VE API client for PHP: the whole API as PHP calls that follow the tree of the API, with results as objects or arrays, tasks and API token or password login.',
      // Brand, logo, GitHub and "Edit page" links, the Corsinvest sidebar group and
      // external links in a new tab come from the shared cv4pve theme.
      plugins: [
        corsinvestTheme({
          repo: 'cv4pve-api-php',
          // Product icon: favicon and header, dark variant for the dark theme.
          icon: { light: '/icon.svg', dark: '/icon-dark.svg' },
          // Install-and-run panel in the home hero. A library, not a release binary: custom targets.
          install: {
            targets: [
              {
                id: 'composer',
                label: 'Composer',
                lines: [
                  '# the latest version is on Packagist',
                  'composer require corsinvest/cv4pve-api-php',
                ],
              },
            ],
          },
        }),
      ],
      lastUpdated: true,
      sidebar: [
        {
          label: 'Start here',
          items: ['getting-started', 'connection', 'permissions', 'troubleshooting'],
        },
        {
          label: 'Concepts',
          items: ['concepts/api-structure', 'concepts/results', 'concepts/indexed-parameters', 'concepts/tasks', 'concepts/errors'],
        },
        {
          label: 'Examples',
          items: ['examples/common-tasks', 'examples/create-vm', 'examples/bulk-operations'],
        },
      ],
    }),
  ],
});
