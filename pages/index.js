import Link from 'next/link';
import products from '../data/products.json';

export default function Home() {
  return (
    <div style={{ maxWidth: '600px', margin: '0 auto', padding: '20px' }}>
      <h1>Daftar Produk Affiliate</h1>
      <ul>
        {products.map((p) => (
          <li key={p.slug} style={{ marginBottom: '15px', borderBottom: '1px solid #eee', paddingBottom: '15px' }}>
            <Link href={`/p/${p.slug}`} style={{ textDecoration: 'none', color: '#333', display: 'flex', alignItems: 'center', gap: '15px' }}>
              <img src={p.image} alt={p.title} style={{ width: '80px', height: '80px', objectFit: 'cover', borderRadius: '8px' }} />
              <div>
                <h3 style={{ margin: '0 0 5px 0', fontSize: '1.1rem' }}>{p.title}</h3>
                <span style={{ color: '#0070f3', fontSize: '0.9rem' }}>Lihat Detail &rarr;</span>
              </div>
            </Link>
          </li>
        ))}
      </ul>
    </div>
  );
}
