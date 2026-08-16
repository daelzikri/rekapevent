#!/usr/bin/env python3
import sys
import os
from PIL import Image
import pillow_heif

def convert_heic_to_jpeg(input_path: str, output_path: str) -> bool:
    try:
        # Register HEIF opener to Pillow
        pillow_heif.register_heif_opener()
        
        if not os.path.exists(input_path):
            print(f"Error: File input '{input_path}' tidak ditemukan.", file=sys.stderr)
            return False

        # Open image
        image = Image.open(input_path)
        
        # Convert mode to RGB if RGBA or P to avoid JPEG conversion errors
        if image.mode in ("RGBA", "P"):
            image = image.convert("RGB")
            
        # Ensure output directory exists
        os.makedirs(os.path.dirname(output_path), exist_ok=True)
        
        # Save as JPEG
        image.save(output_path, format="JPEG", quality=85, optimize=True)
        print(f"Sukses mengonversi '{input_path}' ke '{output_path}'")
        return True
    except Exception as e:
        print(f"Gagal konversi HEIC: {str(e)}", file=sys.stderr)
        return False

if __name__ == "__main__":
    if len(sys.argv) < 3:
        print("Penggunaan: python convert_heic.py <input_heic_path> <output_jpeg_path>", file=sys.stderr)
        sys.exit(1)
        
    in_file = sys.argv[1]
    out_file = sys.argv[2]
    
    success = convert_heic_to_jpeg(in_file, out_file)
    if success:
        sys.exit(0)
    else:
        sys.exit(1)
