import type { PixelCrop } from 'react-image-crop';

export interface CropOutput {
  width: number;
  height: number;
  label: string;
}

// Standard Indonesian pas foto sizes with output dimensions
export const PAS_FOTO_SIZES: Record<string, CropOutput> = {
  '2x3': { width: 200, height: 300, label: '2x3 cm' },
  '3x4': { width: 300, height: 400, label: '3x4 cm' },
  '4x6': { width: 400, height: 600, label: '4x6 cm' },
};

export function getAspectRatio(size: string): number {
  const config = PAS_FOTO_SIZES[size];
  return config ? config.width / config.height : 3 / 4;
}

export async function getCroppedImg(
  image: HTMLImageElement,
  crop: PixelCrop,
  size: string
): Promise<Blob> {
  const canvas = document.createElement('canvas');
  const ctx = canvas.getContext('2d');

  if (!ctx) {
    throw new Error('Failed to get canvas context');
  }

  const config = PAS_FOTO_SIZES[size] || PAS_FOTO_SIZES['3x4'];
  
  // Scale output for high quality (2x for retina displays)
  const scale = 2;
  canvas.width = config.width * scale;
  canvas.height = config.height * scale;

  // Enable image smoothing for high quality
  ctx.imageSmoothingEnabled = true;
  ctx.imageSmoothingQuality = 'high';

  // Draw cropped portion to canvas
  ctx.drawImage(
    image,
    crop.x,
    crop.y,
    crop.width,
    crop.height,
    0,
    0,
    canvas.width,
    canvas.height
  );

  // Convert to Blob with high quality JPEG (95%)
  return new Promise((resolve, reject) => {
    canvas.toBlob(
      (blob) => {
        if (blob) {
          resolve(blob);
        } else {
          reject(new Error('Failed to create blob'));
        }
      },
      'image/jpeg',
      0.95
    );
  });
}
