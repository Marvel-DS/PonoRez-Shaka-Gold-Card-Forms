module.exports = {
  content: [
    "./partials/**/*.php",
    "./suppliers/**/*.php",
    "./controller/**/*.php",
    "./*.php",
    "./*.js"
  ],
  theme: {
    fontFamily: {
      sans: ['Inter', 'ui-sans-serif', 'system-ui'],
      heading: ['Poppins', 'ui-sans-serif', 'system-ui'],
    },
    extend: {
      colors: {
        body: '#090B0A',
      },
      height: {
        '120': '480px',
      },
      maxHeight: {
        '120': '480px',
      },
      duration: {
        '1000': '1s',
      },
      shadow: {
        card: '0 4px 16px rgba(0,0,0,0.08)',
      },
    },
  },
  plugins: [],
}