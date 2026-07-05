/** @type {import('next').NextConfig} */
const path = require('path');

const apiOrigin = (
  process.env.API_INTERNAL_ORIGIN ||
  process.env.NEXT_PUBLIC_API_ORIGIN ||
  'http://localhost:8000'
).replace(/\/$/, '');

const nextConfig = {
  turbopack: {
    root: path.resolve(__dirname),
  },
  async redirects() {
    return [
      { source: '/admin', destination: '/admin/dashboard', permanent: true },
      { source: '/admin/home', destination: '/admin/investors', permanent: true },
      { source: '/admin/statement', destination: '/admin/statement-of-accounts', permanent: true },
      { source: '/admin/valuations', destination: '/admin/portfolio-valuations', permanent: true },
      { source: '/statement-of-account', destination: '/statement-of-accounts', permanent: true },
    ];
  },
  async rewrites() {
    return {
      fallback: [
        {
          source: '/api/:path*',
          destination: `${apiOrigin}/api/:path*`,
        },
      ],
    };
  },
};

module.exports = nextConfig;
