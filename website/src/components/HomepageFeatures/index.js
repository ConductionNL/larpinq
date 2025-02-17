import React from 'react';
import clsx from 'clsx';
import styles from './styles.module.css';

/**
 * List of features displayed on the homepage
 * Each feature has a title and description about the LarpingApp
 */
const FeatureList = [
  {
    title: 'Easy Event Management',
    description: (
      <>
        Create and manage your LARP events with ease. Handle registrations, character sheets, and event schedules all in one place.
      </>
    ),
  },
  {
    title: 'Character Development',
    description: (
      <>
        Build rich character backgrounds, track skills and abilities, and manage character progression throughout your LARP campaign.
      </>
    ),
  },
  {
    title: 'Community Features',
    description: (
      <>
        Connect with other LARPers, join groups, and coordinate with your fellow players. Share stories and experiences within the LARP community.
      </>
    ),
  },
];

/**
 * Component to render a single feature
 * @param {string} title - The title of the feature
 * @param {JSX.Element} description - The description of the feature
 * @returns {JSX.Element} Feature component
 */
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

/**
 * Main component that displays all features on the homepage
 * @returns {JSX.Element} HomepageFeatures component
 */
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