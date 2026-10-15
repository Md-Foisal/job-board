import { AwsClient } from 'aws4fetch';

// Only image files with these extensions can ever be served, and only from
// the one bucket named in B2_BUCKET. Nothing else in the bucket is reachable.
const ALLOWED_KEY = /^[A-Za-z0-9_-]+(\/[A-Za-z0-9_-]+)*\.(jpe?g|png|webp|gif)$/i;

const notFound = () => new Response('Not found', { status: 404 });

export function keyFromPath(pathname) {
  let key;
  try {
    key = decodeURIComponent(pathname.slice(1));
  } catch {
    return null;
  }
  return ALLOWED_KEY.test(key) ? key : null;
}

export default {
  async fetch(request, env) {
    if (request.method !== 'GET' && request.method !== 'HEAD') {
      return new Response('Method not allowed', { status: 405, headers: { Allow: 'GET, HEAD' } });
    }

    const key = keyFromPath(new URL(request.url).pathname);
    if (key === null) return notFound();

    const client = new AwsClient({
      accessKeyId: env.B2_KEY_ID,
      secretAccessKey: env.B2_APP_KEY,
      service: 's3',
      region: env.B2_REGION,
    });

    const headers = {};
    const etag = request.headers.get('If-None-Match');
    if (etag) headers['If-None-Match'] = etag;

    const upstream = await client.fetch(
      `https://${env.B2_BUCKET}.s3.${env.B2_REGION}.backblazeb2.com/${key}`,
      { method: request.method, headers },
    );

    if (upstream.status === 304) {
      return new Response(null, { status: 304, headers: { ETag: etag ?? '' } });
    }

    const type = upstream.headers.get('Content-Type') ?? '';
    if (!upstream.ok || !type.startsWith('image/')) return notFound();

    return new Response(request.method === 'HEAD' ? null : upstream.body, {
      status: 200,
      headers: {
        'Content-Type': type,
        'Content-Length': upstream.headers.get('Content-Length') ?? '',
        ETag: upstream.headers.get('ETag') ?? '',
        // File names are random and never reused, so browsers may keep them for a year.
        'Cache-Control': 'public, max-age=31536000, immutable',
        'X-Content-Type-Options': 'nosniff',
        'Cross-Origin-Resource-Policy': 'cross-origin',
      },
    });
  },
};
