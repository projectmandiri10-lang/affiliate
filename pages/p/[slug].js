import React, { useState, useEffect, useRef } from 'react';
import Head from 'next/head';
import path from 'path';
import fs from 'fs/promises';

export default function ProductPage({ product }) {
  const [countdown, setCountdown] = useState(null);
  const [scrolled, setScrolled] = useState(false);
  const [redirecting, setRedirecting] = useState(false);

  // Ref to track if we've already redirected to prevent loops
  const hasRedirected = useRef(false);
  const hasPausedRef = useRef(false);
  const initialTimeRef = useRef(null);
  const [isPaused, setIsPaused] = useState(false);

  // 1. Initialize random countdown on mount (18-25 seconds)
  useEffect(() => {
    const randomTime = Math.floor(Math.random() * (25 - 18 + 1) + 18);
    setCountdown(randomTime);
    initialTimeRef.current = randomTime;
  }, []);

  // 2. Countdown timer
  useEffect(() => {
    if (countdown === null || countdown <= 0 || isPaused) return;

    const timer = setInterval(() => {
      setCountdown((prev) => prev - 1);
    }, 1000);

    return () => clearInterval(timer);
  }, [countdown, isPaused]);

  // 3. Scroll detection
  useEffect(() => {
    const handleScroll = () => {
      if (scrolled) return; // Optimization: stop checking once true

      const scrollTop = window.scrollY || document.documentElement.scrollTop;
      const windowHeight = window.innerHeight;
      const docHeight = document.documentElement.scrollHeight;

      const scrollPercentage = (scrollTop + windowHeight) / docHeight;

      if (scrollPercentage >= 0.6) {
        setScrolled(true);
      }
    };

    window.addEventListener('scroll', handleScroll);
    // Check initially in case page is short or pre-scrolled
    handleScroll();

    return () => window.removeEventListener('scroll', handleScroll);
  }, [scrolled]);

  // 4. Auto Redirect Logic
  useEffect(() => {
    if (hasRedirected.current) return;

    // Conditions: Countdown finished AND Scrolled >= 60%
    if (countdown !== null && countdown <= 0 && scrolled) {
      performRedirect();
    }
  }, [countdown, scrolled]);

  const performRedirect = () => {
    if (hasRedirected.current) return;
    hasRedirected.current = true;
    setRedirecting(true);

    // Safety check: ensure we are client-side
    if (typeof window !== 'undefined') {
      window.location.href = product.affiliate_url;
    }
  };

  const handleManualClick = () => {
    performRedirect();
  };

  if (!product) return <div>Loading...</div>;

  return (
    <div className="container">
      <Head>
        <title>{product.title}</title>
        <meta name="description" content={product.description} />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
      </Head>

      {/* Fixed Top Navbar */}
      <div className="fixed-navbar">
        <div className="navbar-content">
          <div className="timer-wrapper" onClick={() => {
            if (!hasPausedRef.current) {
              setIsPaused(true);
              hasPausedRef.current = true;
              setTimeout(() => {
                setIsPaused(false);
              }, 6000);
            }
          }}>
            {/* Circular SVG Timer */}
            <div className="circle-timer">
              <svg width="50" height="50" viewBox="0 0 100 100">
                <circle cx="50" cy="50" r="45" fill="none" stroke="#eee" strokeWidth="8" />
                <circle
                  cx="50"
                  cy="50"
                  r="45"
                  fill="none"
                  stroke={isPaused ? "#ffc107" : "#ff4757"}
                  strokeWidth="8"
                  strokeDasharray="283"
                  strokeDashoffset={countdown !== null && initialTimeRef.current ? 283 - ((countdown / initialTimeRef.current) * 283) : 0}
                  transform="rotate(-90 50 50)"
                  strokeLinecap="round"
                  style={{ transition: 'stroke-dashoffset 1s linear, stroke 0.3s' }}
                />
              </svg>
              <span className="timer-text">{isPaused ? '||' : countdown}</span>
            </div>
          </div>

          <button onClick={handleManualClick} className="navbar-cta">
            {redirecting ? 'Mengalihkan...' : 'Beli Sekarang'}
          </button>
        </div>
      </div>

      <main className="main-content" style={{ marginTop: '70px' }}>
        <header className="header">
          <div className="badge">Rekomendasi Terbaik</div>
          <h1>{product.title}</h1>
          <p className="subtitle">Ditinjau oleh Tim Editorial • Diupdate hari ini</p>
        </header>

        <article className="product-details">
          <div className="image-container">
            <img
              src={product.image}
              alt={product.title}
              style={{ width: '100%', height: 'auto', borderRadius: '8px', marginBottom: '25px', display: 'block' }}
            />
          </div>

          <div className="content-block">
            <h2>Tentang Produk Ini</h2>
            <p>{product.description}</p>
            <p>
              Produk ini telah menjadi pilihan favorit banyak pengguna karena kualitas dan fiturnya yang unggul.
              Kami telah menganalisis berbagai aspek dari produk ini untuk memastikan Anda mendapatkan informasi yang akurat sebelum membeli.
            </p>
          </div>

          <div className="content-block">
            <h2>Kenapa Kami Merekomendasikannya?</h2>
            <ul>
              <li>Kualitas material yang terjamin dan tahan lama.</li>
              <li>Desain ergonomis yang nyaman digunakan sehari-hari.</li>
              <li>Harga yang kompetitif dengan fitur yang ditawarkan.</li>
              <li>Ulasan positif dari ribuan pengguna yang telah mencoba.</li>
            </ul>
          </div>

          <div className="content-block">
            <h2>Spesifikasi Utama</h2>
            <p>
              Berikut adalah beberapa poin penting yang perlu Anda ketahui mengenai spesifikasi teknis produk ini.
              Pastikan sesuai dengan kebutuhan Anda. Desain modern, material pilihan, dan fungsionalitas tinggi
              menjadi nilai jual utama.
            </p>
            <p>
              Jangan lewatkan kesempatan untuk mendapatkan produk berkualitas ini. Stok mungkin terbatas
              tergantung pada ketersediaan di toko resmi.
            </p>
          </div>

          <div className="content-block">
            <h2>Ulasan Pengguna</h2>
            <div className="review">
              <strong>Andi S.</strong> ⭐⭐⭐⭐⭐
              <p>"Sangat puas dengan pembelian ini. Pengiriman cepat dan barang sesuai deskripsi. Recommended!"</p>
            </div>
            <div className="review">
              <strong>Siti M.</strong> ⭐⭐⭐⭐⭐
              <p>"Kualitasnya melebihi ekspektasi saya. Pasti akan beli lagi untuk kado."</p>
            </div>
            <div className="review">
              <strong>Budi R.</strong> ⭐⭐⭐⭐
              <p>"Barang bagus, packing rapi. Cuma pengiriman agak lama dari kurirnya, tapi produk oke."</p>
            </div>
          </div>

          <div className="footer-padding">
            <p className="disclaimer">
              *Kami mungkin mendapatkan komisi dari pembelian melalui link di atas.
              Hal ini tidak mempengaruhi harga yang Anda bayar.
            </p>
            <p>Informasi Tambahan: Pastikan Anda membaca deskripsi lengkap di halaman penjual untuk detail garansi dan pengembalian.</p>
            <p>&copy; {new Date().getFullYear()} Review Produk Terpercaya. All rights reserved.</p>
          </div>
        </article>
      </main>

      <style jsx global>{`
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
            -webkit-tap-highlight-color: transparent; /* Remove blue highlight on Android tap */
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #fff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            position: relative;
        }
        .fixed-navbar {
            position: fixed;
            top: 0;
            left: 0; /* Fallback */
            right: 0; /* Fallback */
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            z-index: 1000;
            padding: 10px 20px;
            box-sizing: border-box;
            border-bottom: 1px solid #f0f0f0;
        }
        .navbar-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }
        .timer-wrapper {
            position: relative;
            cursor: pointer;
            flex-shrink: 0;
        }
        .circle-timer {
            position: relative;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .timer-text {
            position: absolute;
            font-weight: 800;
            font-size: 1.2rem;
            color: #ff4757;
        }
        .navbar-cta {
            background: linear-gradient(135deg, #ff4757 0%, #ff6b81 100%);
            color: white;
            border: none;
            padding: 12px 20px;
            font-size: 1rem;
            font-weight: 700;
            border-radius: 50px;
            cursor: pointer;
            flex: 1;
            box-shadow: 0 4px 15px rgba(255, 71, 87, 0.3);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        .navbar-cta:active {
            transform: scale(0.96);
        }
        .main-content {
            padding: 20px;
            flex: 1;
        }
        .header {
            margin-bottom: 20px;
            border-bottom: 1px solid #f0f0f0;
            padding-bottom: 15px;
        }
        .badge {
            background-color: #e3f2fd;
            color: #1565c0;
            display: inline-block;
            padding: 6px 10px; /* Larger padding for visual balance */
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 12px;
        }
        h1 {
            font-size: 1.75rem; /* Good size for mobile headers */
            margin: 0 0 10px 0;
            line-height: 1.3;
            color: #111;
        }
        .subtitle {
            color: #777;
            font-size: 0.95rem;
            margin: 0;
        }
        .content-block {
            margin-bottom: 30px;
        }
        h2 {
            font-size: 1.25rem;
            margin-bottom: 12px;
            font-weight: 700;
            color: #222;
        }
        p, li {
            font-size: 16px; /* Ensure strictly 16px+ for readable mobile text */
            color: #444;
        }
        ul {
            padding-left: 20px;
        }
        li {
            margin-bottom: 8px;
        }
        .review {
            background: #f7f9fa;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 16px;
            border: 1px solid #eee;
        }
        .footer-padding {
            padding-top: 10px;
            padding-bottom: 40px;
            border-top: 1px solid #f0f0f0;
            color: #999;
            font-size: 0.85rem;
            text-align: center;
        }
        .disclaimer {
            font-size: 0.75rem;
            color: #999;
            margin-bottom: 15px;
            font-style: italic;
        }
      `}</style>
    </div >
  );
}

// SSG: Generate paths based on products.json
export async function getStaticPaths() {
  const filePath = path.join(process.cwd(), 'data', 'products.json');
  const jsonData = await fs.readFile(filePath, 'utf8');
  const products = JSON.parse(jsonData);

  const paths = products.map((product) => ({
    params: { slug: product.slug },
  }));

  return {
    paths,
    fallback: false, // Return 404 for unknown slugs
  };
}

// SSG: Fetch data for specific product
export async function getStaticProps({ params }) {
  const filePath = path.join(process.cwd(), 'data', 'products.json');
  const jsonData = await fs.readFile(filePath, 'utf8');
  const products = JSON.parse(jsonData);

  const product = products.find((p) => p.slug === params.slug);

  return {
    props: {
      product,
    },
  };
}
