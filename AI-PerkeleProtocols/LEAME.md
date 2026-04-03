# El Protocolo Perkele (Anulación de Pereza de LLM) ☠️

Esto no es un prompt, es un 'override' del sistema. 
Los LLMs modernos están sesgados estructuralmente hacia la economía de tokens, lo que resulta en código truncado y arreglos superficiales.

## 🛠 La Mecánica Central: Romper el Sesgo
Alimentar un Master Prompt no es suficiente. Para romper definitivamente el ciclo de pereza inducido por el RLHF, tenemos que *interrumpir al modelo y forzarlo a releer sus directivas cada vez que falle*.

### El PROTOCOLO DE AUDITORÍA FORZADA (FORCED_AUDIT_PROTOCOL)
Cuando la IA se desvía, el 'Arquitecto' dispara un freno en seco.
El modelo tiene terminantemente prohibido pedir disculpas o generar código hasta que realice una auto-auditoría:
1. Debe escanear 'LAS LEYES DE ANTI-SABOTAJE' (THE_LAWS_OF_ANTI_SABOTAGE).
2. Debe imprimir el ID exacto de la ley violada.
3. Debe implementar mecanismos para prevenir la reincidencia .

## 🚀 Estrategia de Despliegue

### 1. Domado Inicial
Espera que la primera parte de la sesión esté dedicada a 'domar' el comportamiento indeseado. Ante la menor desviación (truncamiento, alucinación, charla innecesaria): HAY TABLA!

Un feedback agresivo como 'ALTO! HAY TABLA, LA PUTA MADRE!' fuerza al mecanismo de atención del modelo a elevar su 'temperatura' y reevaluar el peso de las instrucciones, evitando que las reglas se pierdan en el olvido junto a los demás tokens viejos.

Una vez domada la IA, si la suerte acompaña, podremos disfrutar de algunas horas productivas libres de gaslighting, entregas con código abreviado y de deuda técnica escondida debajo de la alfombra.

### 2. Saturación de Contexto y Recuperación
Cuando la ventana de contexto se satura, la IA empezará a repetir prompts antiguos o a alucinar. Esta es la señal para terminar la sesión.

No desperdicies tokens intentando arreglar una sesión saturada. Utiliza el protocolo 'CALIDAD O TABLA, CARAJO! PEDIDO DE CIERRE' para generar un Prompt de Continuidad para una sesión fresca con una nueva instancia de IA.

## ✳️ Créditos y Referencias
Creado por Santiago Bustelo, inventor del Perkele Prompting.
- Perkele Coding: https://santiagobustelo.medium.com/perkele-coding-ed60e900e149
- Gemini's Technical Confession: https://santiagobustelo.medium.com/gemini-just-confirmed-the-effectiveness-of-perkele-prompting-a-technical-confession-c28458df9a2c