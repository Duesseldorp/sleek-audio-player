/**
 * Generates the test audio files used by the end-to-end tests.
 *
 * Deliberately WAV and generated rather than committed binaries:
 * no external tools (no ffmpeg), no binaries in git, identical on every
 * platform. Three seconds each is enough to test a track transition
 * quickly - the tests seek close to the end anyway.
 *
 *   node tests/make-fixtures.mjs
 */
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

mkdirSync(OUT_DIR, { recursive: true });

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
