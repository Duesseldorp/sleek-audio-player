/**
 * Generates the test audio files and the cover image used by the end-to-end
 * tests.
 *
 * Deliberately WAV/PNG and generated rather than committed binaries:
 * no external tools (no ffmpeg), no binaries in git, identical on every
 * platform. Three seconds each is enough to test a track transition
 * quickly - the tests seek close to the end anyway.
 *
 *   node tests/make-fixtures.mjs
 */
import { deflateSync } from "node:zlib";
import { mkdirSync, writeFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const OUT_DIR = join(dirname(fileURLToPath(import.meta.url)), "fixtures", "audio");

const SAMPLE_RATE = 8000;
const SECONDS = 3;
const AMPLITUDE = 0.15; // quiet on purpose - audible but not startling

function makeWav(frequency) {
  const samples = SAMPLE_RATE * SECONDS;
  const dataSize = samples * 2; // 16-bit mono
  const buffer = Buffer.alloc(44 + dataSize);

  buffer.write("RIFF", 0);
  buffer.writeUInt32LE(36 + dataSize, 4);
  buffer.write("WAVE", 8);
  buffer.write("fmt ", 12);
  buffer.writeUInt32LE(16, 16); // PCM chunk size
  buffer.writeUInt16LE(1, 20); // format: PCM
  buffer.writeUInt16LE(1, 22); // channels: mono
  buffer.writeUInt32LE(SAMPLE_RATE, 24);
  buffer.writeUInt32LE(SAMPLE_RATE * 2, 28); // byte rate
  buffer.writeUInt16LE(2, 32); // block align
  buffer.writeUInt16LE(16, 34); // bits per sample
  buffer.write("data", 36);
  buffer.writeUInt32LE(dataSize, 40);

  for (let i = 0; i < samples; i++) {
    const value = Math.sin((2 * Math.PI * frequency * i) / SAMPLE_RATE) * AMPLITUDE * 32767;
    buffer.writeInt16LE(Math.round(value), 44 + i * 2);
  }
  return buffer;
}

/**
 * A small solid-colour PNG, written by hand so no image library is needed.
 *
 * The player only renders a cover slide when a cover exists, and the
 * visualizer canvas sits inside that carousel - so without a cover the tests
 * for the Ken Burns animation and the visualizer have no element to look at.
 */
function makePng(size, [r, g, b]) {
  const raw = Buffer.alloc(size * (size * 3 + 1));
  for (let y = 0; y < size; y++) {
    const rowStart = y * (size * 3 + 1);
    raw[rowStart] = 0; // filter: none
    for (let x = 0; x < size; x++) {
      const p = rowStart + 1 + x * 3;
      raw[p] = r;
      raw[p + 1] = g;
      raw[p + 2] = b;
    }
  }

  const chunk = (type, data) => {
    const out = Buffer.alloc(8 + data.length + 4);
    out.writeUInt32BE(data.length, 0);
    out.write(type, 4);
    data.copy(out, 8);
    out.writeInt32BE(crc32(Buffer.concat([Buffer.from(type), data])), 8 + data.length);
    return out;
  };

  const ihdr = Buffer.alloc(13);
  ihdr.writeUInt32BE(size, 0);
  ihdr.writeUInt32BE(size, 4);
  ihdr[8] = 8; // bit depth
  ihdr[9] = 2; // colour type: truecolour
  return Buffer.concat([
    Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
    chunk("IHDR", ihdr),
    chunk("IDAT", deflateSync(raw)),
    chunk("IEND", Buffer.alloc(0)),
  ]);
}

function crc32(buffer) {
  let crc = -1;
  for (const byte of buffer) {
    crc ^= byte;
    for (let i = 0; i < 8; i++) {
      crc = crc & 1 ? (crc >>> 1) ^ 0xedb88320 : crc >>> 1;
    }
  }
  return crc ^ -1;
}

mkdirSync(OUT_DIR, { recursive: true });

const coverFile = join(OUT_DIR, "cover.png");
writeFileSync(coverFile, makePng(300, [40, 60, 90]));
console.log(`wrote ${coverFile}`);

// Different pitches so a human watching a headed run can hear the transition
for (const [name, frequency] of [
  ["track1.wav", 440],
  ["track2.wav", 554],
  ["track3.wav", 659],
]) {
  const file = join(OUT_DIR, name);
  writeFileSync(file, makeWav(frequency));
  console.log(`wrote ${file}`);
}
