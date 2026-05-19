/**
 * LarpingApp landing page.
 *
 * Composes the brand <DetailHero> + <WidgetShelf> from
 * @conduction/docusaurus-preset/components, mirroring the connext page
 * at sites/www/src/pages/apps/larpingapp.mdx.
 *
 * Written as .js (not .mdx) because the docs site has the docs plugin
 * pointed at `path: './'`, and an MDX file in src/pages/ trips the
 * MDX-ESM parser even with the docs plugin's `src/**` exclude — likely
 * a quirk of how mdx-loader's micromark stack reuses parser state
 * across files in this Docusaurus 3.10 + this preset combination.
 * Authoring the page in JSX keeps the same component composition.
 */

import React from 'react';
import Layout from '@theme/Layout';
import {
  DetailHero,
  WidgetShelf,
  AppMock,
} from '@conduction/docusaurus-preset/components';

/* Spike-chart glyph from sites/www/src/pages/apps/larpingapp.mdx —
   line-chart with a sharp peak, the LarpingApp brand glyph. */
const LARPINGAPP_ICON = (
  <svg viewBox="0 0 24 24">
    <path d="M3 12h6l3-7 3 14 3-7h3" />
  </svg>
);

const TAGLINE = (
  <>
    Characters, rules, scenes, and NPC stats for live-action role-play
    (LARP). Build a setting in{' '}
    <span className="next-blue">Nextcloud</span>, share it with your
    group, and run sessions without spreadsheets-with-six-tabs.
  </>
);

function CharacterPanel() {
  const stats = [
    { label: 'STR', val: '60%' },
    { label: 'AGI', val: '80%' },
    { label: 'WIS', val: '45%' },
    { label: 'HP', val: '70%', accent: true },
  ];
  return (
    <div style={{ display: 'flex', gap: 10, alignItems: 'flex-start' }}>
      <div
        style={{
          width: 44,
          height: 50,
          clipPath: 'var(--hex-pointy-top)',
          background: 'var(--c-lavender-300)',
          flexShrink: 0,
        }}
      />
      <div
        style={{
          flex: 1,
          display: 'flex',
          flexDirection: 'column',
          gap: 6,
        }}
      >
        <div
          style={{
            height: 6,
            width: '70%',
            background: 'var(--c-cobalt-700)',
            borderRadius: 1,
          }}
        />
        <div
          style={{
            height: 4,
            width: '50%',
            background: 'var(--c-cobalt-200)',
            borderRadius: 1,
          }}
        />
        {stats.map((row, i) => (
          <div
            key={i}
            style={{ display: 'flex', alignItems: 'center', gap: 6 }}
          >
            <div
              style={{
                fontFamily: 'var(--conduction-typography-font-family-code)',
                fontSize: 9,
                letterSpacing: '0.05em',
                color: 'var(--c-cobalt-400)',
                width: 22,
              }}
            >
              {row.label}
            </div>
            <div
              style={{
                flex: 1,
                height: 4,
                background: 'var(--c-cobalt-100)',
                borderRadius: 1,
                position: 'relative',
              }}
            >
              <div
                style={{
                  height: '100%',
                  width: row.val,
                  background: row.accent
                    ? 'var(--c-mint-500)'
                    : 'var(--c-cobalt-700)',
                  borderRadius: 1,
                }}
              />
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

function SessionsPanel() {
  const rows = [
    { date: '14', tone: 'var(--c-mint-500)' },
    { date: '21', tone: 'var(--c-lavender-300)' },
    { date: '28', tone: 'var(--c-forest-300)' },
    { date: '04', tone: 'var(--c-cobalt-300)' },
  ];
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
      {rows.map((row, i) => (
        <div
          key={i}
          style={{ display: 'flex', alignItems: 'center', gap: 8 }}
        >
          <div
            style={{
              width: 22,
              height: 25,
              clipPath: 'var(--hex-pointy-top)',
              background: row.tone,
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              flexShrink: 0,
            }}
          >
            <div
              style={{
                fontFamily: 'var(--conduction-typography-font-family-code)',
                fontSize: 9,
                fontWeight: 700,
                color: 'var(--c-cobalt-700)',
              }}
            >
              {row.date}
            </div>
          </div>
          <div
            style={{
              flex: 1,
              display: 'flex',
              flexDirection: 'column',
              gap: 3,
            }}
          >
            <div
              style={{
                height: 4,
                width: '75%',
                background: 'var(--c-cobalt-700)',
                borderRadius: 1,
              }}
            />
            <div
              style={{
                height: 3,
                width: '55%',
                background: 'var(--c-cobalt-200)',
                borderRadius: 1,
              }}
            />
          </div>
        </div>
      ))}
    </div>
  );
}

function ScenesPanel() {
  const labels = [
    'The moot at Ravensford',
    'Siege of Old Mill',
    'Hunt for the wyrm',
    'Council in Stonehollow',
  ];
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
      {labels.map((_, i) => (
        <div
          key={i}
          style={{
            display: 'flex',
            alignItems: 'center',
            gap: 8,
            padding: '4px 0',
            borderBottom:
              i < labels.length - 1 ? '1px solid var(--c-cobalt-50)' : 'none',
          }}
        >
          <span
            style={{
              width: 10,
              height: 11,
              clipPath: 'var(--hex-pointy-top)',
              background:
                i === 0 ? 'var(--c-orange-knvb)' : 'var(--c-mint-500)',
            }}
          />
          <div
            style={{
              flex: 1,
              display: 'flex',
              flexDirection: 'column',
              gap: 2,
            }}
          >
            <div
              style={{
                height: 4,
                width: '70%',
                background: 'var(--c-cobalt-700)',
                borderRadius: 1,
              }}
            />
            <div
              style={{
                height: 3,
                width: '50%',
                background: 'var(--c-cobalt-200)',
                borderRadius: 1,
              }}
            />
          </div>
          <div
            style={{
              height: 3,
              width: 22,
              background: 'var(--c-cobalt-200)',
              borderRadius: 1,
            }}
          />
        </div>
      ))}
    </div>
  );
}

const WIDGETS = [
  {
    title: 'My character',
    desc: 'Character sheet snapshot. Stats, hit points, current scene, faction allegiance. A typed record against a schema; change the schema and every character migrates with it.',
    panel: <CharacterPanel />,
  },
  {
    title: 'Upcoming sessions',
    desc: 'Calendar events tagged for the campaign. Players see only their own faction sessions. One Nextcloud login for the whole campaign.',
    panel: <SessionsPanel />,
  },
  {
    title: 'Recent scenes',
    desc: 'Last sessions with location, NPCs, and outcome. Continuity becomes searchable; "who was at the moot in week six" is a click, not a memory test.',
    panel: <ScenesPanel />,
  },
];

export default function Home() {
  return (
    <Layout
      title="LarpingApp, character and event management for LARP groups"
      description="Characters, rules, scenes, and NPC stats for live-action role-play. Build a setting in Nextcloud and run sessions without spreadsheets-with-six-tabs."
    >
      <main className="marketing-page">
        <DetailHero
          appId="larpingapp"
          background="cobalt"
          status={{ label: 'Beta', color: 'var(--c-orange-knvb)' }}
          version="v0.1"
          locales="NL · EN"
          title="LarpingApp"
          tagline={TAGLINE}
          primaryCta={{
            label: 'Install from app store',
            href: 'https://apps.nextcloud.com/apps/larpingapp',
            tone: 'orange',
          }}
          secondaryCta={{ label: 'Read the docs', href: '/docs/FEATURES' }}
          tertiaryCta={{
            label: 'View on GitHub',
            href: 'https://github.com/ConductionNL/larpingapp',
          }}
          iconColor="var(--c-orange-knvb)"
          icon={LARPINGAPP_ICON}
          illustration={<AppMock app="larpingapp" />}
        />

        <WidgetShelf
          eyebrow="Widgets we ship"
          title="Your campaign on the Nextcloud home screen."
          lede="Install LarpingApp and these widgets show up on every player's dashboard. Character to the left, calendar to the right, scene log below."
          widgets={WIDGETS}
        />
      </main>
    </Layout>
  );
}
