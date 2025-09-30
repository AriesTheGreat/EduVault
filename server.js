const express = require('express');
const { OAuth2Client } = require('google-auth-library');
const jwt = require('jsonwebtoken');
const app = express();

const CLIENT_ID = '18457313587-e2iq0jtc0qr7kousep09aj60kugk2lfe.apps.googleusercontent.com'; // Replace with your Google Client ID
const CLIENT_SECRET = '****JaP0'; 
const REDIRECT_URI = 'http://localhost:3000/auth/google/callback';

app.get('/auth/google/callback', async (req, res) => {
  const code = req.query.code;
  const oAuth2Client = new OAuth2Client(CLIENT_ID, CLIENT_SECRET, REDIRECT_URI);

  try {
    const { tokens } = await oAuth2Client.getToken(code);
    oAuth2Client.setCredentials(tokens);

    // Get user info
    const ticket = await oAuth2Client.verifyIdToken({
      idToken: tokens.id_token,
      audience: CLIENT_ID,
    });
    const payload = ticket.getPayload();

    // Restrict to organizational email
    if (payload.email !== '@parsu.edu.ph') {
      return res.send('Registration failed: Only organizational email (@parsu.edu.ph) is allowed.');
    }

    // You can now use payload.email, payload.name, etc.
    // Redirect to dashboard or do further processing
    res.send(`Hello, ${payload.email}! Google registration successful.`);
  } catch (err) {
    res.status(500).send('Authentication failed.');
  }
});

app.listen(3000, () => console.log('Server running on http://localhost:3000'));
