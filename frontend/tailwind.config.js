/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', 'sans-serif'],
        'orbitron': ['Orbitron', 'sans-serif'],
        'rajdhani': ['Rajdhani', 'sans-serif'],
        'tech': ['"Share Tech Mono"', 'monospace'],
      },
      colors: {
        primary: {
          50: '#f0f9ff',
          100: '#e0f2fe',
          200: '#bae6fd',
          300: '#7dd3fc',
          400: '#38bdf8',
          500: '#0ea5e9',
          600: '#0284c7',
          700: '#0369a1',
          800: '#075985',
          900: '#0c4a6e',
        },
        'neon-pink': '#ff00ff',
        'neon-cyan': '#00eaff',
        'neon-yellow': '#ffef00',
        'neon-purple': '#7a00ff',
        'neon-green': '#39ff14',
        'bg-dark-1': '#060b18',
        'bg-dark-2': '#0a102a',
        'bg-dark-3': '#0a0f1f',
        'cyber-blue': '#0066ff',
        'cyber-pink': '#ff0080',
      },
      boxShadow: {
        'neon-pink': '0 0 15px rgba(255, 0, 255, 0.8)',
        'neon-cyan': '0 0 15px rgba(0, 234, 255, 0.8)',
        'neon-yellow': '0 0 15px rgba(255, 239, 0, 0.8)',
        'neon-purple': '0 0 15px rgba(122, 0, 255, 0.8)',
        'neon-green': '0 0 15px rgba(57, 255, 20, 0.8)',
        'cyber-glow': '0 0 20px rgba(255, 0, 255, 0.5), 0 0 40px rgba(0, 234, 255, 0.3)',
        'cyber-intense': '0 0 30px rgba(255, 0, 255, 0.9), 0 0 60px rgba(0, 234, 255, 0.6)',
      },
      animation: {
        'neon-glow': 'neonGlow 2s ease-in-out infinite alternate',
        'hologram-pulse': 'hologramPulse 3s ease-in-out infinite',
        'grid-move': 'gridMove 20s linear infinite',
        'flicker': 'flicker 0.5s ease-in-out infinite alternate',
        'cyber-scan': 'cyberScan 2s linear infinite',
        'float': 'float 3s ease-in-out infinite',
      },
      keyframes: {
        neonGlow: {
          '0%': { 
            textShadow: '0 0 10px rgba(255, 0, 255, 0.8), 0 0 20px rgba(255, 0, 255, 0.5)',
            filter: 'brightness(1)',
          },
          '100%': { 
            textShadow: '0 0 20px rgba(255, 0, 255, 1), 0 0 40px rgba(0, 234, 255, 0.8)',
            filter: 'brightness(1.2)',
          },
        },
        hologramPulse: {
          '0%, 100%': { opacity: '0.8', transform: 'scale(1)' },
          '50%': { opacity: '1', transform: 'scale(1.02)' },
        },
        gridMove: {
          '0%': { transform: 'translateY(0)' },
          '100%': { transform: 'translateY(50px)' },
        },
        flicker: {
          '0%, 100%': { opacity: '1' },
          '50%': { opacity: '0.9' },
        },
        cyberScan: {
          '0%': { transform: 'translateY(-100%)' },
          '100%': { transform: 'translateY(100%)' },
        },
        float: {
          '0%, 100%': { transform: 'translateY(0px)' },
          '50%': { transform: 'translateY(-10px)' },
        },
      },
      backgroundImage: {
        'cyber-grid': 'linear-gradient(rgba(255, 0, 255, 0.1) 1px, transparent 1px), linear-gradient(90deg, rgba(0, 234, 255, 0.1) 1px, transparent 1px)',
        'neon-gradient': 'linear-gradient(135deg, #7a00ff 0%, #ff00ff 50%, #00eaff 100%)',
        'cyber-gradient': 'linear-gradient(135deg, #060b18 0%, #0a102a 50%, #0a0f1f 100%)',
      },
      backgroundSize: {
        'grid': '50px 50px',
      },
    },
  },
  plugins: [],
}

