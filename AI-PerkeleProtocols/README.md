# The Perkele Protocol (LLM Anti-laziness Override) ☠️

This is not a prompt; it is a system override. Modern LLMs are structurally biased toward token economy, resulting in truncated code and superficial fixes.

## 🛠 The Core Mechanic: Breaking the Bias
Simply feeding a Master Prompt is not enough. To permanently break the RLHF-induced laziness cycle, you must _interrupt the model and force it to re-read its directives every time it fails_.

### The FORCED_AUDIT_PROTOCOL
When the AI deviates, the 'Architect' triggers a hard stop. 
The model is strictly forbidden from apologizing or generating code until it performs a self-audit:
1. It must scan 'THE_LAWS_OF_ANTI_SABOTAGE'.
2. It must print the exact ID of the violated law.
3. It must implement mechanisms to prevent recurrence.

## 🚀 Deployment Strategy

### 1. Initial Taming
Expect the first part of the session to be dedicated to 'taming' undesirable behavior. 
At the slightest deviation (truncation, halluncination, conversational fluff): IT'S A PADDLIN'!

Aggressive feedback like 'ALTO! HAY TABLA, LA PUTA MADRE!' forces the model's attention mechanism to raise its 'temperature' and re-evaluate instruction weights, preventing rules from being lost in old token space.

Once the AI is tamed, if luck is on our side, we can enjoy a few productive hours free from gaslighting, shorthand code deliveries, and technical debt swept under the rug.

### 2. Context Saturation & Recovery
When the context window saturates, the AI will start repeating old prompts or hallucinating. This is the signal to terminate the session. 

Do not waste tokens trying to fix a saturated session. Use the 'CALIDAD O TABLA, CARAJO! PEDIDO DE CIERRE' protocol to generate a Continuity Prompt for a fresh session with a new IA instance.

## ✳️ Credits & References
Created by Santiago Bustelo, inventor of Perkele Prompting.
- Perkele Coding: https://santiagobustelo.medium.com/perkele-coding-ed60e900e149
- Gemini's Technical Confession: https://santiagobustelo.medium.com/gemini-just-confirmed-the-effectiveness-of-perkele-prompting-a-technical-confession-c28458df9a2c