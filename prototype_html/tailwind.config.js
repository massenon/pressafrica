/** Africa Global Press design tokens (see docs/design-system/DESIGN-SYSTEM.md) */
module.exports = {
  darkMode: ['class', '[data-theme="dark"]'],
  content: ['./fr/**/*.html', './en/**/*.html', './admin/**/*.html', './assets/js/**/*.js', './index.html'],
  theme: {
    extend: {
      colors: {
        brand: { DEFAULT: '#013079', soft: '#345994', ink: '#00143a', sky: '#8fb2ff' },
        flash: '#ffec00',
        alert: '#e02200',
        ink: { DEFAULT: '#000000', 2: '#141414', 3: '#282828' },
        muted: '#949494',
        meta: { DEFAULT: '#5c5c5c', dark: '#a9a9a9' },   // AA-safe text for dates/bylines (muted #949494 is decorative only)
        paper: { DEFAULT: '#ffffff', 2: '#f5f5f5', 3: '#e8e8e8' },
      },
      fontFamily: {
        serif: ['Alegreya', 'Georgia', 'serif'],
        sans: ['"Encode Sans"', 'system-ui', 'sans-serif'],
      },
      borderRadius: { ds: '8px', card: '12px' },
      spacing: { 13: '3.25rem' },
      boxShadow: {
        soft: '0 4px 30px 0 rgba(0,0,0,.3)',
        lift: '0 12px 32px -12px rgba(0,20,58,.35)',
      },
      maxWidth: { page: '1240px', prose: '68ch' },
      transitionTimingFunction: { spring: 'cubic-bezier(.22,1,.36,1)', ease: 'cubic-bezier(.4,0,.2,1)' },
      keyframes: {
        'fade-up': { from: { opacity: 0, transform: 'translateY(14px)' }, to: { opacity: 1, transform: 'none' } },
        'page-in': { from: { opacity: 0 }, to: { opacity: 1 } },
      },
      animation: {
        'fade-up': 'fade-up .7s cubic-bezier(.22,1,.36,1) both',
        'page-in': 'page-in .35s ease both',
      },
    },
  },
  plugins: [],
};
