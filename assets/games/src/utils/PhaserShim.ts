// When using externals, webpack replaces 'phaser' imports with the global Phaser
// We need to directly access the global instead of importing
declare const Phaser: any;

// Get Phaser from the global scope (loaded via UMD)
const PhaserGlobal: any = (typeof window !== 'undefined' ? (window as any).Phaser : null) || Phaser;

export default PhaserGlobal;