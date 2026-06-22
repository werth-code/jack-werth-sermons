#!/usr/bin/env python3
"""
Transcribe sermon MP3s to text + word-level timestamps (faster-whisper, CPU).

Output per sermon: data/transcripts/<archive-id>.json
  { "id", "passage", "text", "words": [ {"w": word, "s": start_sec, "e": end_sec}, ... ],
    "model", "duration" }

Usage:
  .venv-whisper/bin/python scripts/transcribe.py --pilot 3                 # first 3 (by date) as a test
  .venv-whisper/bin/python scripts/transcribe.py --model distil-large-v3 audio/"2018.08.19.M Colossians 4.17-18 - Jack Werth.mp3"
  .venv-whisper/bin/python scripts/transcribe.py --all                     # everything not yet done
"""
import sys, json, os, time, glob

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.join(HERE, "..")
OUT  = os.path.join(ROOT, "data", "transcripts")
AUDIO = os.path.join(ROOT, "audio")

def load_catalog():
    cat = json.load(open(os.path.join(ROOT, "data", "sermons.json")))
    by_title = {c["title"]: c for c in cat}   # MP3 basename == title
    return cat, by_title

def parse_args(argv):
    model = "distil-large-v3"; files = []; pilot = 0; do_all = False
    i = 0
    while i < len(argv):
        a = argv[i]
        if a == "--model": model = argv[i+1]; i += 2
        elif a == "--pilot": pilot = int(argv[i+1]); i += 2
        elif a == "--all": do_all = True; i += 1
        else: files.append(a); i += 1
    return model, files, pilot, do_all

def main():
    model_name, files, pilot, do_all = parse_args(sys.argv[1:])
    os.makedirs(OUT, exist_ok=True)
    cat, by_title = load_catalog()

    # Resolve which audio files to transcribe.
    if pilot or do_all:
        ordered = sorted(cat, key=lambda c: (c["date"], c["service_code"]))
        targets = ordered if do_all else ordered[:pilot]
        jobs = []
        for c in targets:
            mp3 = os.path.join(AUDIO, c["title"] + ".mp3")
            outp = os.path.join(OUT, c["identifier"] + ".json")
            if os.path.exists(mp3) and not os.path.exists(outp):
                jobs.append((mp3, c))
    else:
        jobs = []
        for f in files:
            base = os.path.splitext(os.path.basename(f))[0]
            c = by_title.get(base)
            if not c: print(f"  ! no catalog match for {base}"); continue
            jobs.append((f, c))

    if not jobs:
        print("Nothing to transcribe (all done, or no matching audio)."); return

    from faster_whisper import WhisperModel
    print(f"Loading {model_name} (int8, CPU)… (first run downloads the model)")
    model = WhisperModel(model_name, device="cpu", compute_type="int8")

    for n, (mp3, c) in enumerate(jobs, 1):
        t0 = time.time()
        print(f"[{n}/{len(jobs)}] {c['passage']}  ({c['title']})")
        segments, info = model.transcribe(mp3, language="en", word_timestamps=True,
                                          vad_filter=True, beam_size=1)
        words, parts = [], []
        for seg in segments:
            parts.append(seg.text)
            for w in (seg.words or []):
                words.append({"w": w.word, "s": round(w.start, 2), "e": round(w.end, 2)})
            sys.stdout.write(f"\r   …{seg.end:.0f}/{info.duration:.0f}s"); sys.stdout.flush()
        json.dump({"id": c["identifier"], "passage": c["passage"], "text": "".join(parts).strip(),
                   "words": words, "model": model_name, "duration": round(info.duration, 1)},
                  open(os.path.join(OUT, c["identifier"] + ".json"), "w"), ensure_ascii=False)
        rt = info.duration / max(1, time.time() - t0)
        print(f"\r   {len(words)} words, {info.duration:.0f}s audio in {time.time()-t0:.0f}s ({rt:.1f}x realtime)   ")

if __name__ == "__main__":
    main()
