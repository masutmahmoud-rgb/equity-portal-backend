import '../styles.css'
import { SWRConfig } from 'swr'

export default function MyApp({ Component, pageProps }) {
  return (
    <SWRConfig value={{
      revalidateOnFocus: false,
      revalidateOnReconnect: false,
      revalidateIfStale: false,
    }}>
      <Component {...pageProps} />
    </SWRConfig>
  )
}
