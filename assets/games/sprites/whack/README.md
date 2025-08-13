# Whack-a-Question Sprites

This directory contains placeholder sprite information for the Whack-a-Question game.

## Required Sprites

- `hole.png` - Mole hole sprite (circular brown hole in ground)
- `mole.png` - Mole sprite (brown mole character)
- `background.png` - Game background (grass field or playground)

## Sprite Specifications

### hole.png
- Size: 60x60 pixels
- Format: PNG with transparency
- Content: Brown circular hole with darker edges
- Style: Cartoon-like, matching theme aesthetic

### mole.png
- Size: 50x50 pixels
- Format: PNG with transparency
- Content: Cute brown mole character
- Style: Friendly cartoon appearance

### background.png
- Size: 800x600 pixels
- Format: PNG or JPG
- Content: Grassy field or playground scene
- Style: Colorful, child-friendly environment

## Fallback Graphics

The game will use fallback colored shapes if these sprites are not available:
- Holes: Brown circles (0x8B4513)
- Background: Theme-based color fill
- Answer containers: Rounded rectangles with theme colors

## Theme Integration

Sprites should be designed to work with all three themes:
- Playful: Bright, vibrant colors
- Minimal Pro: Clean, professional aesthetic
- Neon Cyber: Futuristic, glowing effects

Consider creating theme-specific variations or using programmable shaders for theme adaptation.