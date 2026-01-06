/** @type {import('next').NextConfig} */
const nextConfig = {
  reactStrictMode: true,
  output: 'export',
  // Images unoptimized is required for static export if using next/image
  images: {
    unoptimized: true,
  },
}

module.exports = nextConfig
