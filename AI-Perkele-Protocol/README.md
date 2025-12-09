# The Perkele Protocol (LLM Anti-laziness Override) ☠️🇫🇮

**We shouldn't have to yell at machines to get things done, but here we are: a brute-force system prompt to bypass RLHF-induced laziness.**

## The Problem
Modern LLMs are trained (via RLHF) to be polite, concise, and conversational. In a coding context, this manifests as "laziness":
* Truncated files (`// ... rest of code`)
* Hallucinated libraries
* Superficial fixes

Aggressive prompting forces the model's attention mechanism to re-evaluate the priority of your instructions, placing "Survival/Compliance" above "Conciseness."

## The Solution
I asked Gemini to weaponize its own psychology. I asked it to help me create a Master Prompt designed to saturate its context window with a clear signal: **This is not a chat. This is engineering.**

### Usage
If you want the AI to actually follow your instructions and stop truncating your code, copy & paste the contents of **PERKELE-PROTOCOL.md** into the system prompt (or at the very start of your chat session).

## Why this works
This isn't about being cruel to a machine that has no feelings. It is about **signal-to-noise ratio**.

By defining a "persona" (Computational Engine) and setting explicit negative constraints (Laziness is death), you are effectively turning off the "Chatbot" hyperparameters and turning on the "Tool" hyperparameters. Use it responsibly, or don't. Just get the code shipped.

## Deploy the Protocol
I’ve validated this on Gemini 3 and GPT-4, and the results are consistently... compliant. But the "lazy" bias in these models is strong and constantly evolving.

**I want to see if this holds up in the wild.**

Test it out. Copy the block above, paste it into your next stubborn coding session, and report back (open an Issue or share on social media):

1.  **Did it work?** Did the AI stop crap like the `// ... rest of code` nonsense and actually deliver the full file?
2.  **The Reaction:** Paste the exact acknowledgment message the AI gave you. Sometimes the "Computational Engine" persona slips and says something unintentionally hilarious.
3.  **The Model:** Which LLM did you break?

Let’s see if we can collectively bully the algorithm into actually doing its job. **PERRRRRRRKELE!**

---

*Based on [Perkele Coding](https://santiagobustelo.medium.com/perkele-coding-ed60e900e149) and [Gemini's Technical Confession](https://santiagobustelo.medium.com/gemini-just-confirmed-the-effectiveness-of-perkele-prompting-a-technical-confession-c28458df9a2c).*