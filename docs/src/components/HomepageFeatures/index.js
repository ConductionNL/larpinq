import React from 'react';
import clsx from 'clsx';
import styles from './styles.module.css';

const FeatureList = [
  {
    title: 'Character Management',
    description: (
      <>
        Create and manage player characters with dynamic stat calculation, skill trees, and XP tracking. Background approval workflow keeps your game world consistent.
      </>
    ),
  },
  {
    title: 'Skills, Items & Effects',
    description: (
      <>
        Define skills, items, and conditions with a powerful effects system. Stats update automatically when characters equip items or gain conditions.
      </>
    ),
  },
  {
    title: 'Event Subscriptions',
    description: (
      <>
        Manage LARP events and player subscriptions. Track attendance, assign characters, and generate printable PDF character sheets for every session.
      </>
    ),
  },
];

function Feature({title, description}) {
  return (
    <div className={clsx('col col--4')}>
      <div className="text--center padding-horiz--md">
        <h3>{title}</h3>
        <p>{description}</p>
      </div>
    </div>
  );
}

export default function HomepageFeatures() {
  return (
    <section className={styles.features}>
      <div className="container">
        <div className="row">
          {FeatureList.map((props, idx) => (
            <Feature key={idx} {...props} />
          ))}
        </div>
      </div>
    </section>
  );
}
