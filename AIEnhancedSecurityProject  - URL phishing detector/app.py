import pickle
import pandas as pd
from urllib.parse import urlparse
import gradio as gr
import matplotlib.pyplot as plt


def extract_features(url: str) -> dict:
    parsed = urlparse(url)
    return {
        'url_length': len(url),
        'count_at': url.count('@'),
        'count_dots': url.count('.'),
        'count_hyphens': url.count('-'),
        'has_https': 1 if parsed.scheme == 'https' else 0,
        'path_length': len(parsed.path),
        'query_length': len(parsed.query)
    }


def predict_and_explain(url: str, thr: float):
    url = url if url.startswith(('http://','https://')) else 'http://' + url
    feat_df = pd.DataFrame([extract_features(url)])
    prob = model.predict_proba(feat_df)[0,1]
    label = '🚨 Phishing' if prob >= thr else '✅ Legitimate'

    contributions = feat_df.values[0] * model.coef_[0]
    feature_names = feat_df.columns.tolist()
    contrib_df = pd.DataFrame({'feature': feature_names, 'contribution': contributions}).set_index('feature')
    contrib_df = contrib_df.sort_values('contribution', key=lambda x: x.abs(), ascending=False)

    fig, ax = plt.subplots()
    contrib_df['contribution'].plot.bar(ax=ax)
    ax.set_title('Feature Contributions')
    plt.tight_layout()

    return label, round(prob,2), fig


if __name__ == '__main__':
    model, saved_threshold = pickle.load(open('phishing_model.pkl','rb'))

    with gr.Blocks(title="Phishing Detection") as demo:
        gr.Markdown("# 🚀 Phishing Website Detection")
        gr.Markdown("Use the slider to adjust the phishing decision threshold.")

        with gr.Row():
            url_input = gr.Textbox(label="URL", placeholder="https://example.com")
            threshold_slider = gr.Slider(minimum=0.0, maximum=1.0, step=0.01, value=saved_threshold, label="Threshold")
            analyze_btn = gr.Button("Analyze", variant="primary")

        with gr.Row():
            pred_out = gr.Textbox(label="Prediction", interactive=False)
            prob_out = gr.Textbox(label="Probability", interactive=False)

        contrib_plot = gr.Plot(label="Feature Contributions")

        analyze_btn.click(
            fn=predict_and_explain,
            inputs=[url_input, threshold_slider],
            outputs=[pred_out, prob_out, contrib_plot]
        )

    demo.launch()
