# rpg-system delta

## ADDED Requirements

### Requirement: Characters buy items with in-game money (REQ-RPG-001)

An item SHALL be able to carry a price in gold, silver and copper. Buying an item for a character MUST add the item to the character and take the price from the character's money in one write. A purchase the character cannot pay MUST be refused and change nothing.

#### Scenario: Mirela buys a rope

- GIVEN item "Rope" costs 2 silver and "Mirela the Wanderer" has 5 silver
- WHEN Anna buys "Rope" for "Mirela the Wanderer" on the character page
- THEN "Mirela the Wanderer" carries "Rope"
- AND she has 3 silver left

#### Scenario: Not enough money

- GIVEN item "Crown of Aldmoor" costs 500 gold and "Mirela the Wanderer" has 3 gold
- WHEN Anna tries to buy it
- THEN the purchase is refused
- AND "Mirela the Wanderer" still has 3 gold and does not carry the crown
