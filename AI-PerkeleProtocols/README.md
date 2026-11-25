[Versión en español](LEAME.md)

# The Perkele Protocols (LLM Anti-Laziness Override) ☠️🇫🇮

<p align="center">
  <img src="../docs/assets/sb-quality-or-paddlin.png" alt="Quality or That's a Paddlin'" width="512">
</p>

These are not conversational prompts; they are operational system overrides. Modern LLMs are structurally biased toward token economy and polite compliance, resulting in truncated code, hallucinated assumptions, and superficial fixes.

## 🛠 Core Mechanic: Breaking RLHF Inertia

Simply feeding a Master Prompt is not enough. To permanently break the RLHF-induced laziness cycle, you must **interrupt the model and force it to re-read its directives every time it deviates**.

### The Forced Audit Protocol (`PADDLIN` / `TABLA` / `PERKELE`)
When the AI deviates, the Architect triggers a hard stop (`PADDLIN` / `TABLA` / `PERKELE`). The model is strictly forbidden from apologizing or emitting code until it executes a 5-step audit:
1. Re-read the active skill and list every violated directive.
2. State the exact technical root cause in one direct sentence, owning agency.
3. Define the concrete operational check to prevent recurrence.
4. Evaluate whether the failure exposed a gap in the skill itself and propose an update if needed.
5. Apply the fix under strict protocol compliance.

## 📂 Included Protocols

- **`ai0-perkele-architect.en.md`:** Primary software architecture and `_ai0` code delivery skill (*Quality, or That's a Paddlin'*). Enforces zero technical debt, two-step architectural gates, IIFE + EventBus JS modularity, CSS token SSOT, and deterministic `CREATE` / `PATCH` / `MOVE` / `DISABLE` / `DISCARD` blocks.
- **`perkele-session-handoff.en.json`:** Emergency context-saturation recovery prompt that generates a deterministic handoff manifest and terminal payload script for a fresh AI session.
- **`PERKELE-PHANTOM-WRITER-V2.02.es.md`:** Ghostwriting and style-absorption protocol (Spanish) engineered to preserve authorial voice and eliminate LLM clichés.
- **`anti-bullshit protocol.en.txt` / `.es.txt`:** Standalone epistemic control rules enforcing `UNKNOWN DATA` / `DATO DESCONOCIDO` thresholds over probabilistic guessing.

## 🚀 Deployment Strategy

1. **Initial Taming:** Expect the first turns of a session to calibrate model compliance. At the slightest truncation or assumption, trigger `PADDLIN`, `TABLA`, or `PERKELE` to raise instruction attention weights.
2. **Context Saturation & Recovery:** When a long session saturates and the model begins repeating old patterns, stop immediately and run `perkele-session-handoff.en.json` to migrate cleanly to a fresh session.

## ✳️ Credits & References
Created by **Santiago Bustelo**, pioneer of Perkele Prompting (inspired by Linus Torvalds' *Management by Perkele*).
- Perkele Coding: https://santiagobustelo.medium.com/perkele-coding-ed60e900e149
- The AI Perkele Suit: https://medium.com/santiago-bustelo-in-english/the-ai-perkele-suit-a-framework-for-measuring-real-world-ai-pain-03e3d64f1ff8
- Gemini's Technical Confession: https://santiagobustelo.medium.com/gemini-just-confirmed-the-effectiveness-of-perkele-prompting-a-technical-confession-c28458df9a2c