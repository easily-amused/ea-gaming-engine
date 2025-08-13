# Target Trainer Sprites

This directory contains placeholder sprite information for the Target Trainer game.

## Required Sprites

### Core Game Assets
- **target.png** - Main target sprite (64x64px recommended)
- **target-hit.png** - Target hit animation sprite
- **crosshair.png** - Crosshair cursor sprite (32x32px)
- **background.png** - Game background
- **bullet.png** - Bullet/projectile sprite (8x8px)

### Visual Effects
- **explosion.png** - Hit explosion effect
- **miss-effect.png** - Miss indicator effect
- **combo-badge.png** - Combo multiplier badge

### UI Elements
- **accuracy-meter.png** - Accuracy meter background
- **score-multiplier.png** - Score multiplier indicator

## Fallback Graphics

The game will create simple geometric fallbacks if these sprites are not found:
- Targets: Red circles with white rings
- Crosshair: White cross with black outline
- Bullets: Small white circles
- Effects: Colored particle systems

## Sprite Specifications

- All sprites should be PNG format with transparency
- Target sprites should have clear hit zones
- Crosshair should be small enough not to obstruct view
- Effects should be lightweight for performance