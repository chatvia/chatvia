// Ambient declarations for stylesheet-only package entry points that are
// imported purely for their side effects. TypeScript 6 type-checks
// side-effect imports by default, and these packages ship no type
// declarations for their CSS exports. Keep this file free of imports/exports
// so the declarations stay global.
declare module '@formkit/themes/genesis';
